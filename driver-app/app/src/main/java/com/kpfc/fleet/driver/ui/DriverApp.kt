package com.kpfc.fleet.driver.ui

import android.widget.Toast
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import com.kpfc.fleet.driver.data.MockDriverData
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.ui.auth.LoginScreen
import com.kpfc.fleet.driver.ui.dashboard.DashboardScreen
import com.kpfc.fleet.driver.ui.theme.KpfcDriverTheme
import com.kpfc.fleet.driver.ui.trip.CompleteTripDialog
import com.kpfc.fleet.driver.ui.trip.ReturnToBaseDialog
import com.kpfc.fleet.driver.ui.trip.TripExecutionScreen

enum class AppScreen {
    LOGIN,
    DASHBOARD,
    TRIP_EXECUTION
}

@Composable
fun DriverApp() {
    val context = LocalContext.current

    // Navigation and Auth state
    var currentScreen by remember { mutableStateOf(AppScreen.LOGIN) }
    var currentDriverEmail by remember { mutableStateOf<String?>(null) }

    // Live Trip state for interactive UI prototype
    var activeTrip by remember { mutableStateOf<TripDto?>(MockDriverData.createSampleActiveTrip()) }
    val upcomingTrips by remember { mutableStateOf(MockDriverData.createSampleUpcomingTrips()) }
    val assignedVehicle by remember { mutableStateOf(MockDriverData.sampleVehicle) }

    // Dialog states
    var showReturnDialog by remember { mutableStateOf(false) }
    var showCompleteDialog by remember { mutableStateOf(false) }

    KpfcDriverTheme {
        when (currentScreen) {
            AppScreen.LOGIN -> {
                LoginScreen(
                    onLoginSuccess = { email ->
                        currentDriverEmail = email
                        currentScreen = AppScreen.DASHBOARD
                        Toast.makeText(context, "Logged in as $email", Toast.LENGTH_SHORT).show()
                    }
                )
            }

            AppScreen.DASHBOARD -> {
                DashboardScreen(
                    driverName = currentDriverEmail?.substringBefore('@')?.replace('.', ' ')?.replaceFirstChar { it.uppercase() } ?: "Driver David",
                    vehicle = assignedVehicle,
                    activeTrip = activeTrip,
                    upcomingTrips = upcomingTrips,
                    onOpenTrip = {
                        currentScreen = AppScreen.TRIP_EXECUTION
                    },
                    onLogout = {
                        currentDriverEmail = null
                        currentScreen = AppScreen.LOGIN
                    }
                )
            }

            AppScreen.TRIP_EXECUTION -> {
                activeTrip?.let { trip ->
                    TripExecutionScreen(
                        trip = trip,
                        onBack = {
                            currentScreen = AppScreen.DASHBOARD
                        },
                        onMarkStopArrived = { stopId ->
                            // Update stop status in local state
                            val updatedStops = trip.stops.map { stop ->
                                if (stop.id == stopId) {
                                    stop.copy(status = "arrived", arrivedAt = "Just now")
                                } else {
                                    stop
                                }
                            }
                            activeTrip = trip.copy(stops = updatedStops)
                            Toast.makeText(context, "Stop marked as arrived!", Toast.LENGTH_SHORT).show()
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
                        val undelivered = trip.stops.filter { it.status == "pending" }
                        ReturnToBaseDialog(
                            undeliveredStops = undelivered,
                            onDismiss = { showReturnDialog = false },
                            onSubmit = { reason, _ ->
                                showReturnDialog = false
                                activeTrip = trip.copy(status = "returning_to_base")
                                Toast.makeText(
                                    context,
                                    "Return request submitted to Fleet Manager.",
                                    Toast.LENGTH_LONG
                                ).show()
                            }
                        )
                    }

                    // Complete Trip Dialog
                    if (showCompleteDialog) {
                        CompleteTripDialog(
                            startingMileage = trip.startingMileage ?: 62450,
                            onDismiss = { showCompleteDialog = false },
                            onConfirm = { endingMileage ->
                                showCompleteDialog = false
                                val tripDistance = endingMileage - (trip.startingMileage ?: 62450)
                                activeTrip = trip.copy(
                                    status = "completed",
                                    endingMileage = endingMileage,
                                    tripMileage = tripDistance
                                )
                                Toast.makeText(
                                    context,
                                    "Trip Completed! Total Distance: $tripDistance km",
                                    Toast.LENGTH_LONG
                                ).show()
                                currentScreen = AppScreen.DASHBOARD
                            }
                        )
                    }
                }
            }
        }
    }
}
