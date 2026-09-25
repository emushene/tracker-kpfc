package com.kpfc.fleet.driver.ui

import android.widget.Toast
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.lifecycle.viewmodel.compose.viewModel
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.ui.auth.LoginScreen
import com.kpfc.fleet.driver.ui.dashboard.DashboardScreen
import com.kpfc.fleet.driver.ui.theme.KpfcDriverTheme
import com.kpfc.fleet.driver.ui.trip.CompleteTripDialog
import com.kpfc.fleet.driver.ui.trip.ReturnToBaseDialog
import com.kpfc.fleet.driver.ui.trip.StartTripDialog
import com.kpfc.fleet.driver.ui.trip.TripExecutionScreen
import com.kpfc.fleet.driver.ui.map.LeafletMapScreen

enum class AppScreen {
    LOGIN,
    DASHBOARD,
    TRIP_EXECUTION,
    TRIP_MAP
}

@Composable
fun DriverApp(
    viewModel: DriverViewModel = viewModel()
) {
    val context = LocalContext.current
    val uiState by viewModel.uiState.collectAsState()

    var currentScreen by remember { mutableStateOf(AppScreen.DASHBOARD) }

    // Dialog states
    var showReturnDialog by remember { mutableStateOf(false) }
    var showCompleteDialog by remember { mutableStateOf(false) }
    var tripToStart by remember { mutableStateOf<TripDto?>(null) }

    // Synchronize screen state with authentication
    LaunchedEffect(uiState.isAuthenticated) {
        if (uiState.isAuthenticated && currentScreen == AppScreen.LOGIN) {
            currentScreen = AppScreen.DASHBOARD
        } else if (!uiState.isAuthenticated && currentScreen != AppScreen.LOGIN) {
            currentScreen = AppScreen.LOGIN
        }
    }

    // Display toast messages from ViewModel
    LaunchedEffect(uiState.errorMessage) {
        uiState.errorMessage?.let { error ->
            Toast.makeText(context, error, Toast.LENGTH_LONG).show()
            viewModel.clearMessages()
        }
    }

    LaunchedEffect(uiState.successMessage) {
        uiState.successMessage?.let { success ->
            Toast.makeText(context, success, Toast.LENGTH_SHORT).show()
            viewModel.clearMessages()
        }
    }

    KpfcDriverTheme {
        when (currentScreen) {
            AppScreen.LOGIN -> {
                LoginScreen(
                    isLoading = uiState.isLoading,
                    errorMessage = uiState.errorMessage,
                    serverBaseUrl = uiState.serverBaseUrl,
                    onLogin = { email, password ->
                        viewModel.login(email, password)
                    },
                    onUpdateServerUrl = { newUrl ->
                        viewModel.updateServerUrl(newUrl)
                        Toast.makeText(context, "Server URL updated to $newUrl", Toast.LENGTH_SHORT).show()
                    }
                )
            }

            AppScreen.DASHBOARD -> {
                DashboardScreen(
                    driverName = uiState.currentUser?.name ?: "Driver",
                    vehicle = uiState.assignedVehicle,
                    activeTrip = uiState.activeTrip,
                    upcomingTrips = uiState.upcomingTrips,
                    isLoading = uiState.isLoading,
                    onRefresh = {
                        viewModel.loadDashboard()
                    },
                    onOpenTrip = {
                        currentScreen = AppScreen.TRIP_EXECUTION
                    },
                    onStartUpcomingTrip = { trip ->
                        tripToStart = trip
                    },
                    onLogout = {
                        viewModel.logout {
                            currentScreen = AppScreen.LOGIN
                        }
                    }
                )

                // Dialog to start planned trip
                tripToStart?.let { trip ->
                    StartTripDialog(
                        trip = trip,
                        onDismiss = { tripToStart = null },
                        onConfirm = { mileage, locationName ->
                            tripToStart = null
                            viewModel.startTrip(
                                trip = trip,
                                startingMileage = mileage,
                                latitude = null,
                                longitude = null,
                                locationName = locationName,
                                onSuccess = {
                                    currentScreen = AppScreen.TRIP_EXECUTION
                                }
                            )
                        }
                    )
                }
            }

            AppScreen.TRIP_EXECUTION -> {
                val activeTrip = uiState.activeTrip
                if (activeTrip != null) {
                    TripExecutionScreen(
                        trip = activeTrip,
                        isLoading = uiState.isLoading,
                        onBack = {
                            currentScreen = AppScreen.DASHBOARD
                        },
                        onRefresh = {
                            viewModel.refreshActiveTrip()
                        },
                        onViewMap = {
                            currentScreen = AppScreen.TRIP_MAP
                        },
                        onMarkStopArrived = { stopId ->
                            viewModel.markStopArrived(stopId)
                        },
                        onRequestReturnToBase = {
                            showReturnDialog = true
                        },
                        onCompleteTrip = {
                            showCompleteDialog = true
                        }
                    )

                    // Return to Base Dialog
                    if (showReturnDialog) {
                        val undelivered = activeTrip.stops.filter { it.status != "arrived" && it.status != "completed" }
                        ReturnToBaseDialog(
                            undeliveredStops = undelivered,
                            onDismiss = { showReturnDialog = false },
                            onSubmit = { reason, notes ->
                                showReturnDialog = false
                                viewModel.submitReturnToBase(
                                    tripId = activeTrip.id,
                                    reason = reason,
                                    latitude = null,
                                    longitude = null,
                                    locationName = null
                                )
                            }
                        )
                    }

                    // Complete Trip Dialog
                    if (showCompleteDialog) {
                        CompleteTripDialog(
                            startingMileage = activeTrip.startingMileage ?: 0,
                            onDismiss = { showCompleteDialog = false },
                            onConfirm = { endingMileage ->
                                showCompleteDialog = false
                                viewModel.completeTrip(
                                    tripId = activeTrip.id,
                                    endingMileage = endingMileage,
                                    onSuccess = {
                                        currentScreen = AppScreen.DASHBOARD
                                    }
                                )
                            }
                        )
                    }
                } else {
                    // If no active trip is present, return to dashboard
                    LaunchedEffect(Unit) {
                        currentScreen = AppScreen.DASHBOARD
                    }
                }
            }

            AppScreen.TRIP_MAP -> {
                val activeTrip = uiState.activeTrip
                if (activeTrip != null) {
                    LeafletMapScreen(
                        trip = activeTrip,
                        onBack = { currentScreen = AppScreen.TRIP_EXECUTION }
                    )
                } else {
                    LaunchedEffect(Unit) { currentScreen = AppScreen.DASHBOARD }
                }
            }
        }
    }
}
