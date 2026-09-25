package com.kpfc.fleet.driver.ui.dashboard

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.VehicleDto
import com.kpfc.fleet.driver.ui.theme.KpfcGreenSuccess
import com.kpfc.fleet.driver.ui.theme.KpfcNavyDark
import com.kpfc.fleet.driver.ui.theme.KpfcNavyLight
import com.kpfc.fleet.driver.ui.theme.KpfcNavyPrimary

@Composable
fun DashboardScreen(
    driverName: String,
    vehicle: VehicleDto?,
    activeTrip: TripDto?,
    upcomingTrips: List<TripDto>,
    onOpenTrip: (TripDto) -> Unit,
    onLogout: () -> Unit
) {
    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MaterialTheme.colorScheme.background
    ) {
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Header: Driver profile & status
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Welcome back,",
                            fontSize = 14.sp,
                            color = Color.Gray
                        )
                        Text(
                            text = driverName,
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = KpfcNavyDark
                        )
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Box(
                                modifier = Modifier
                                    .size(8.dp)
                                    .background(KpfcGreenSuccess, shape = CircleShape)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "Online • Fleet Telematics Active",
                                fontSize = 12.sp,
                                color = KpfcGreenSuccess,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }

                    IconButton(onClick = onLogout) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Sign Out",
                            tint = Color.Gray
                        )
                    }
                }
            }

            // Assigned Vehicle Card
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                    elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
                ) {
                    Column(modifier = Modifier.padding(16.dp)) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "ASSIGNED VEHICLE",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.Gray
                            )
                            Box(
                                modifier = Modifier
                                    .background(color = KpfcNavyLight, shape = RoundedCornerShape(6.dp))
                                    .padding(horizontal = 8.dp, vertical = 2.dp)
                            ) {
                                Text(
                                    text = "Ready",
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = KpfcNavyPrimary
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(8.dp))

                        Text(
                            text = vehicle?.plateNumber ?: "No vehicle assigned",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = KpfcNavyDark
                        )

                        Text(
                            text = vehicle?.deviceName ?: "—",
                            fontSize = 13.sp,
                            color = Color.DarkGray
                        )

                        Spacer(modifier = Modifier.height(8.dp))

                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = Icons.Default.LocationOn,
                                contentDescription = null,
                                tint = KpfcNavyPrimary,
                                modifier = Modifier.size(16.dp)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Text(
                                text = vehicle?.locationName ?: "Awaiting GPS Fix",
                                fontSize = 13.sp,
                                color = Color.Gray
                            )
                        }
                    }
                }
            }

            // Active Trip Hero Card
            item {
                if (activeTrip != null) {
                    val completedStops = activeTrip.stops.count { it.status == "completed" }
                    val totalStops = activeTrip.stops.size
                    val progress = if (totalStops > 0) completedStops.toFloat() / totalStops else 0f

                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(containerColor = KpfcNavyPrimary),
                        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
                    ) {
                        Column(modifier = Modifier.padding(20.dp)) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween
                            ) {
                                Text(
                                    text = "ACTIVE MISSION",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color(0xFF93C5FD)
                                )
                                Text(
                                    text = activeTrip.tripNumber,
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Color.White
                                )
                            }

                            Spacer(modifier = Modifier.height(12.dp))

                            Text(
                                text = "Current Status: ${activeTrip.status.replace('_', ' ').uppercase()}",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Color.White
                            )

                            Text(
                                text = "Started: ${activeTrip.actualStart ?: "—"} • Mileage: ${activeTrip.startingMileage ?: 0} km",
                                fontSize = 12.sp,
                                color = Color(0xFFCBD5E1)
                            )

                            Spacer(modifier = Modifier.height(16.dp))

                            LinearProgressIndicator(
                                progress = { progress },
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(8.dp),
                                color = KpfcGreenSuccess,
                                trackColor = Color(0xFF1E293B)
                            )

                            Spacer(modifier = Modifier.height(6.dp))

                            Text(
                                text = "$completedStops of $totalStops stops completed",
                                fontSize = 12.sp,
                                color = Color(0xFFCBD5E1)
                            )

                            Spacer(modifier = Modifier.height(16.dp))

                            Button(
                                onClick = { onOpenTrip(activeTrip) },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(containerColor = Color.White)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.PlayArrow,
                                    contentDescription = null,
                                    tint = KpfcNavyPrimary
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(
                                    text = "View & Execute Stops",
                                    color = KpfcNavyPrimary,
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }
                }
            }

            // Upcoming Scheduled Trips
            item {
                Text(
                    text = "Upcoming Scheduled Trips",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = KpfcNavyDark
                )
            }

            items(upcomingTrips) { trip ->
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(containerColor = Color.White),
                    elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                text = trip.tripNumber,
                                fontSize = 15.sp,
                                fontWeight = FontWeight.Bold,
                                color = KpfcNavyDark
                            )
                            Text(
                                text = "Planned: ${trip.plannedStart ?: "TBD"}",
                                fontSize = 13.sp,
                                color = Color.Gray
                            )
                        }

                        Box(
                            modifier = Modifier
                                .background(Color(0xFFF1F5F9), RoundedCornerShape(6.dp))
                                .padding(horizontal = 8.dp, vertical = 4.dp)
                        ) {
                            Text(
                                text = "Scheduled",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Medium,
                                color = Color.DarkGray
                            )
                        }
                    }
                }
            }
        }
    }
}
