package com.kpfc.fleet.driver.ui

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.kpfc.fleet.driver.data.api.RetrofitClient
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.data.model.UserDto
import com.kpfc.fleet.driver.data.model.VehicleDto
import com.kpfc.fleet.driver.data.repository.DriverRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class DriverUiState(
    val isAuthenticated: Boolean = false,
    val currentUser: UserDto? = null,
    val activeTrip: TripDto? = null,
    val upcomingTrips: List<TripDto> = emptyList(),
    val assignedVehicle: VehicleDto? = null,
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null,
    val serverBaseUrl: String = RetrofitClient.baseUrl
)

class DriverViewModel(
    application: Application
) : AndroidViewModel(application) {

    private val repository: DriverRepository = DriverRepository(application)

    private val sampleVehicle = VehicleDto(
        id = 101L,
        plateNumber = "KDG 456Z",
        deviceName = "Van 04 - Nairobi Branch",
        imei = "864201948201948",
        locationName = "Nairobi Central Depot",
        latitude = -1.286389,
        longitude = 36.817223
    )

    private val sampleStops = listOf(
        TripStopDto(
            id = 1L,
            tripId = 501L,
            sequence = 1,
            stopType = "pickup",
            locationName = "Nairobi Central Depot (Gate 3)",
            address = "Industrial Area, Commercial St.",
            latitude = -1.3000,
            longitude = 36.8300,
            contactPhone = "+254 700 111 222",
            deliveryInstructions = "Collect 4 cartons of dispatch medical supplies.",
            status = "completed",
            arrivedAt = "08:15 AM"
        ),
        TripStopDto(
            id = 2L,
            tripId = 501L,
            sequence = 2,
            stopType = "delivery",
            locationName = "KPFC Westlands Branch",
            address = "Mpaka Road, Westlands",
            latitude = -1.2650,
            longitude = 36.8050,
            contactPhone = "+254 711 333 444",
            deliveryInstructions = "Deliver to receiving pharmacist. Gate code 4482.",
            status = "pending",
            arrivedAt = null
        )
    )

    private val sampleActiveTrip = TripDto(
        id = 501L,
        tripNumber = "TRP-2026-0042",
        driverId = "drv-9001",
        status = "in_progress",
        plannedStart = "Today at 08:00 AM",
        actualStart = "Today at 08:10 AM",
        startingMileage = 62450,
        endingMileage = null,
        tripMileage = null,
        vehicle = sampleVehicle,
        stops = sampleStops
    )

    private val _uiState = MutableStateFlow(
        DriverUiState(
            isAuthenticated = true,
            currentUser = UserDto(id = 1L, name = "Driver David", email = "driver.david@kpfc.co.ke", role = "driver", driverId = "drv-9001"),
            activeTrip = sampleActiveTrip,
            upcomingTrips = emptyList(),
            assignedVehicle = sampleVehicle
        )
    )
    val uiState: StateFlow<DriverUiState> = _uiState.asStateFlow()

    fun updateServerUrl(url: String) {
        RetrofitClient.setCustomBaseUrl(url)
        _uiState.update { it.copy(serverBaseUrl = RetrofitClient.baseUrl) }
    }

    fun clearMessages() {
        _uiState.update { it.copy(errorMessage = null, successMessage = null) }
    }

    fun login(email: String, password: String, onComplete: (Boolean) -> Unit = {}) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }

            repository.login(email.trim(), password).fold(
                onSuccess = { user ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            isAuthenticated = true,
                            currentUser = user,
                            successMessage = "Signed in as ${user.name}"
                        )
                    }
                    loadDashboard()
                    onComplete(true)
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "Login failed"
                        )
                    }
                    onComplete(false)
                }
            )
        }
    }

    fun logout(onComplete: () -> Unit = {}) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true) }
            repository.logout()
            _uiState.update {
                DriverUiState(serverBaseUrl = RetrofitClient.baseUrl)
            }
            onComplete()
        }
    }

    fun loadDashboard() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true) }

            // 1. Fetch active trip
            val activeTripResult = repository.getActiveTrip()
            val activeTrip = activeTripResult.getOrNull()

            // 2. Fetch upcoming trips
            val upcomingTripsResult = repository.getUpcomingTrips()
            val upcomingTrips = upcomingTripsResult.getOrDefault(emptyList())

            // 3. Fetch assigned vehicle
            val vehicleResult = repository.getAssignedVehicle()
            val vehicle = vehicleResult.getOrNull() ?: activeTrip?.vehicle

            _uiState.update {
                it.copy(
                    isLoading = false,
                    activeTrip = activeTrip,
                    upcomingTrips = upcomingTrips,
                    assignedVehicle = vehicle,
                    errorMessage = activeTripResult.exceptionOrNull()?.message
                )
            }
        }
    }

    fun refreshActiveTrip() {
        viewModelScope.launch {
            repository.getActiveTrip().onSuccess { trip ->
                _uiState.update { it.copy(activeTrip = trip) }
            }
        }
    }

    fun startTrip(
        trip: TripDto,
        startingMileage: Int?,
        latitude: Double?,
        longitude: Double?,
        locationName: String?,
        onSuccess: () -> Unit = {}
    ) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }

            repository.startTrip(trip.id, startingMileage, latitude, longitude, locationName).fold(
                onSuccess = { startedTrip ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            activeTrip = startedTrip,
                            successMessage = "Trip ${startedTrip.tripNumber} started!"
                        )
                    }
                    onSuccess()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "Failed to start trip"
                        )
                    }
                }
            )
        }
    }

    fun markStopArrived(stopId: Long, onSuccess: () -> Unit = {}) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }

            repository.markStopArrived(stopId).fold(
                onSuccess = { updatedStop ->
                    // Update stop in active trip list
                    _uiState.update { state ->
                        val currentTrip = state.activeTrip
                        if (currentTrip != null) {
                            val updatedStops = currentTrip.stops.map {
                                if (it.id == stopId) updatedStop else it
                            }
                            state.copy(
                                isLoading = false,
                                activeTrip = currentTrip.copy(stops = updatedStops),
                                successMessage = "Stop marked as arrived!"
                            )
                        } else {
                            state.copy(isLoading = false)
                        }
                    }
                    onSuccess()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "Failed to mark stop arrived"
                        )
                    }
                }
            )
        }
    }

    fun submitReturnToBase(
        tripId: Long,
        reason: String,
        latitude: Double?,
        longitude: Double?,
        locationName: String?,
        onSuccess: () -> Unit = {}
    ) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }

            repository.requestReturnToBase(tripId, reason, latitude, longitude, locationName).fold(
                onSuccess = {
                    _uiState.update { state ->
                        state.copy(
                            isLoading = false,
                            activeTrip = state.activeTrip?.copy(status = "returning_to_base"),
                            successMessage = "Return-to-base request submitted to Fleet Manager."
                        )
                    }
                    onSuccess()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "Return request failed"
                        )
                    }
                }
            )
        }
    }

    fun completeTrip(tripId: Long, endingMileage: Int, onSuccess: () -> Unit = {}) {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, errorMessage = null) }

            repository.completeTrip(tripId, endingMileage).fold(
                onSuccess = { completedTrip ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            activeTrip = null, // Trip is now completed
                            successMessage = "Trip ${completedTrip.tripNumber} completed! Mileage recorded."
                        )
                    }
                    loadDashboard()
                    onSuccess()
                },
                onFailure = { error ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            errorMessage = error.message ?: "Failed to complete trip"
                        )
                    }
                }
            )
        }
    }
}
