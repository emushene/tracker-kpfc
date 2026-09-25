package com.kpfc.fleet.driver.data.repository

import android.content.Context
import com.google.gson.Gson
import com.kpfc.fleet.driver.data.api.RetrofitClient
import com.kpfc.fleet.driver.data.db.DriverDatabase
import com.kpfc.fleet.driver.data.db.toDto
import com.kpfc.fleet.driver.data.db.toEntity
import com.kpfc.fleet.driver.data.model.ApiResponse
import com.kpfc.fleet.driver.data.model.CompleteTripRequest
import com.kpfc.fleet.driver.data.model.LoginRequest
import com.kpfc.fleet.driver.data.model.ReturnToBaseRequestDto
import com.kpfc.fleet.driver.data.model.StartTripRequest
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.data.model.UserDto
import com.kpfc.fleet.driver.data.model.VehicleDto
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import retrofit2.Response

/**
 * Repository implementing an offline-first strategy:
 * - Reads return cached data immediately, then refresh from network.
 * - Writes always go to the network first; on success the local cache is updated.
 * - If the network is unreachable the cached value is returned (stale-while-offline).
 */
class DriverRepository(context: Context) {

    private val api get() = RetrofitClient.apiService
    private val gson = Gson()

    private val db = DriverDatabase.getInstance(context)
    private val vehicleDao = db.vehicleDao()
    private val tripDao = db.tripDao()
    private val stopDao = db.tripStopDao()

    // ─── Helpers ──────────────────────────────────────────────────────────

    private fun <T> parseErrorMessage(response: Response<T>): String {
        return try {
            val errorBody = response.errorBody()?.string()
            if (!errorBody.isNullOrBlank()) {
                val parsed = gson.fromJson(errorBody, ApiResponse::class.java)
                parsed.message ?: "Server returned error ${response.code()}"
            } else {
                "Server returned error ${response.code()}"
            }
        } catch (e: Exception) {
            "Server returned HTTP ${response.code()}"
        }
    }

    // ─── Auth (no caching needed) ──────────────────────────────────────────

    suspend fun login(email: String, password: String): Result<UserDto> = withContext(Dispatchers.IO) {
        try {
            val response = api.login(LoginRequest(email, password))
            if (response.isSuccessful && response.body()?.data != null) {
                Result.success(response.body()!!.data!!)
            } else {
                Result.failure(Exception(parseErrorMessage(response)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(e.localizedMessage ?: "Network connection failed"))
        }
    }

    suspend fun logout(): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            api.logout()
        } catch (_: Exception) { /* best-effort */ }
        RetrofitClient.clearSession()
        clearLocalCache()
        Result.success(Unit)
    }

    suspend fun getMe(): Result<UserDto> = withContext(Dispatchers.IO) {
        try {
            val response = api.getMe()
            if (response.isSuccessful && response.body()?.data != null) {
                Result.success(response.body()!!.data!!)
            } else {
                Result.failure(Exception(parseErrorMessage(response)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(e.localizedMessage ?: "Failed to load driver profile"))
        }
    }

    // ─── Active Trip (offline-first) ───────────────────────────────────────

    /**
     * Returns the cached active trip immediately, then attempts a network refresh.
     * The caller is responsible for calling this in a pattern that handles both
     * the immediate cache result and the refreshed network result.
     */
    suspend fun getActiveTrip(): Result<TripDto?> = withContext(Dispatchers.IO) {
        // Try network first
        return@withContext try {
            val response = api.getActiveTrip()
            if (response.isSuccessful) {
                val networkTrip = response.body()?.data
                if (networkTrip != null) {
                    // Cache trip + its stops
                    tripDao.upsert(networkTrip.toEntity())
                    if (networkTrip.stops.isNotEmpty()) {
                        stopDao.upsertAll(networkTrip.stops.map { it.toEntity() })
                    } else {
                        // Fetch stops separately if not embedded
                        val stopsResponse = api.getTripStops(networkTrip.id)
                        if (stopsResponse.isSuccessful) {
                            val stops = stopsResponse.body()?.data ?: emptyList()
                            stopDao.upsertAll(stops.map { it.toEntity() })
                            return@withContext Result.success(networkTrip.copy(stops = stops))
                        }
                    }
                }
                Result.success(networkTrip)
            } else {
                // Network failed — serve from cache
                serveCachedActiveTrip()
            }
        } catch (e: Exception) {
            // Offline — serve from cache
            serveCachedActiveTrip()
        }
    }

    private suspend fun serveCachedActiveTrip(): Result<TripDto?> {
        val entity = tripDao.getActiveTrip() ?: return Result.success(null)
        val stops = stopDao.getStopsForTrip(entity.id).map { it.toDto() }
        val vehicle = vehicleDao.getAssignedVehicle()?.toDto()
        return Result.success(entity.toDto(stops = stops, vehicle = vehicle))
    }

    // ─── Upcoming Trips (offline-first) ────────────────────────────────────

    suspend fun getUpcomingTrips(): Result<List<TripDto>> = withContext(Dispatchers.IO) {
        return@withContext try {
            val response = api.getUpcomingTrips()
            if (response.isSuccessful) {
                val trips = response.body()?.data ?: emptyList()
                // Cache all upcoming trips
                tripDao.upsertAll(trips.map { it.toEntity() })
                Result.success(trips)
            } else {
                // Serve from cache
                val cached = tripDao.getUpcomingTrips().map { it.toDto() }
                Result.success(cached)
            }
        } catch (e: Exception) {
            val cached = tripDao.getUpcomingTrips().map { it.toDto() }
            Result.success(cached)
        }
    }

    // ─── Assigned Vehicle (offline-first) ──────────────────────────────────

    suspend fun getAssignedVehicle(): Result<VehicleDto?> = withContext(Dispatchers.IO) {
        return@withContext try {
            val response = api.getAssignedVehicle()
            if (response.isSuccessful) {
                val vehicle = response.body()?.data
                if (vehicle != null) {
                    vehicleDao.upsert(vehicle.toEntity())
                }
                Result.success(vehicle)
            } else {
                Result.success(vehicleDao.getAssignedVehicle()?.toDto())
            }
        } catch (e: Exception) {
            Result.success(vehicleDao.getAssignedVehicle()?.toDto())
        }
    }

    // ─── Trip Actions (network-first, then update cache) ───────────────────

    suspend fun startTrip(
        tripId: Long,
        startingMileage: Int?,
        latitude: Double?,
        longitude: Double?,
        locationName: String?
    ): Result<TripDto> = withContext(Dispatchers.IO) {
        try {
            val response = api.startTrip(
                tripId,
                StartTripRequest(startingMileage, latitude, longitude, locationName)
            )
            if (response.isSuccessful && response.body()?.data != null) {
                val trip = response.body()!!.data!!
                tripDao.upsert(trip.toEntity())
                Result.success(trip)
            } else {
                Result.failure(Exception(parseErrorMessage(response)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(e.localizedMessage ?: "Failed to start trip"))
        }
    }

    suspend fun getTripStops(tripId: Long): Result<List<TripStopDto>> = withContext(Dispatchers.IO) {
        return@withContext try {
            val response = api.getTripStops(tripId)
            if (response.isSuccessful) {
                val stops = response.body()?.data ?: emptyList()
                stopDao.upsertAll(stops.map { it.toEntity() })
                Result.success(stops)
            } else {
                val cached = stopDao.getStopsForTrip(tripId).map { it.toDto() }
                Result.success(cached)
            }
        } catch (e: Exception) {
            val cached = stopDao.getStopsForTrip(tripId).map { it.toDto() }
            Result.success(cached)
        }
    }

    suspend fun markStopArrived(stopId: Long): Result<TripStopDto> = withContext(Dispatchers.IO) {
        try {
            val response = api.arriveStop(stopId)
            if (response.isSuccessful && response.body()?.data != null) {
                val updatedStop = response.body()!!.data!!
                // Update cache immediately
                stopDao.upsert(updatedStop.toEntity())
                Result.success(updatedStop)
            } else {
                Result.failure(Exception(parseErrorMessage(response)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(e.localizedMessage ?: "Failed to mark stop as arrived"))
        }
    }

    suspend fun requestReturnToBase(
        tripId: Long,
        reason: String,
        latitude: Double?,
        longitude: Double?,
        locationName: String?
    ): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val response = api.returnToBase(
                tripId,
                ReturnToBaseRequestDto(reason, latitude, longitude, locationName)
            )
            if (response.isSuccessful) {
                // Optimistically update cached trip status
                tripDao.getActiveTrip()?.let { entity ->
                    tripDao.upsert(entity.copy(status = "returning_to_base"))
                }
                Result.success(Unit)
            } else {
                Result.failure(Exception(parseErrorMessage(response)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(e.localizedMessage ?: "Failed to submit return-to-base request"))
        }
    }

    suspend fun completeTrip(tripId: Long, endingMileage: Int): Result<TripDto> = withContext(Dispatchers.IO) {
        try {
            val response = api.completeTrip(tripId, CompleteTripRequest(endingMileage))
            if (response.isSuccessful && response.body()?.data != null) {
                val completedTrip = response.body()!!.data!!
                // Update cache to completed status then prune
                tripDao.upsert(completedTrip.toEntity())
                tripDao.pruneTerminated()
                Result.success(completedTrip)
            } else {
                Result.failure(Exception(parseErrorMessage(response)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(e.localizedMessage ?: "Failed to complete trip"))
        }
    }

    // ─── Cache Management ──────────────────────────────────────────────────

    private suspend fun clearLocalCache() {
        vehicleDao.clearAll()
        tripDao.clearAll()
        // trip_stops cascade-delete via FK
    }
}
