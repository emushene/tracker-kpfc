package com.kpfc.fleet.driver.data.db

import androidx.room.Dao
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import kotlinx.coroutines.flow.Flow

@Dao
interface VehicleDao {
    @Query("SELECT * FROM vehicles LIMIT 1")
    suspend fun getAssignedVehicle(): VehicleEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(vehicle: VehicleEntity)

    @Query("DELETE FROM vehicles")
    suspend fun clearAll()
}

@Dao
interface TripDao {
    /** Active trip is any trip that is not scheduled/completed/cancelled. */
    @Query(
        """SELECT * FROM trips
           WHERE status NOT IN ('scheduled', 'completed', 'cancelled')
           ORDER BY cachedAt DESC LIMIT 1"""
    )
    suspend fun getActiveTrip(): TripEntity?

    /** Upcoming trips are those still in 'scheduled' status. */
    @Query(
        """SELECT * FROM trips
           WHERE status = 'scheduled'
           ORDER BY plannedStart ASC"""
    )
    suspend fun getUpcomingTrips(): List<TripEntity>

    @Query("SELECT * FROM trips WHERE id = :id")
    fun observeTrip(id: Long): Flow<TripEntity?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(trips: List<TripEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(trip: TripEntity)

    /** Remove completed / old trips to keep cache lean. */
    @Query("DELETE FROM trips WHERE status IN ('completed', 'cancelled')")
    suspend fun pruneTerminated()

    @Query("DELETE FROM trips")
    suspend fun clearAll()
}

@Dao
interface TripStopDao {
    @Query("SELECT * FROM trip_stops WHERE tripId = :tripId ORDER BY sequence ASC")
    suspend fun getStopsForTrip(tripId: Long): List<TripStopEntity>

    @Query("SELECT * FROM trip_stops WHERE tripId = :tripId ORDER BY sequence ASC")
    fun observeStopsForTrip(tripId: Long): Flow<List<TripStopEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsertAll(stops: List<TripStopEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(stop: TripStopEntity)

    @Query("UPDATE trip_stops SET status = :status, arrivedAt = :arrivedAt WHERE id = :stopId")
    suspend fun updateStatus(stopId: Long, status: String, arrivedAt: String?)

    @Query("DELETE FROM trip_stops WHERE tripId = :tripId")
    suspend fun clearForTrip(tripId: Long)
}
