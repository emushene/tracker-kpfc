package com.kpfc.fleet.driver.ui.trip

import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.border
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
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.ui.theme.KpfcAmberWarning
import com.kpfc.fleet.driver.ui.theme.KpfcGreenSuccess
import com.kpfc.fleet.driver.ui.theme.KpfcNavyDark
import com.kpfc.fleet.driver.ui.theme.KpfcNavyLight
import com.kpfc.fleet.driver.ui.theme.KpfcNavyPrimary
import com.kpfc.fleet.driver.ui.theme.KpfcRedDanger

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TripExecutionScreen(
    trip: TripDto,
    isLoading: Boolean = false,
    onBack: () -> Unit,
    onViewMap: () -> Unit = {},
    onMarkStopArrived: (stopId: Long) -> Unit,
    onRequestReturnToBase: () -> Unit,
    onCompleteTrip: () -> Unit,
    onRefresh: () -> Unit = {}
) {
    val context = LocalContext.current

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = trip.tripNumber,
                            fontSize = 17.sp,
                            fontWeight = FontWeight.Bold,
                            color = Color.White
                        )
                        Text(
                            text = "Vehicle: ${trip.vehicle?.plateNumber ?: "—"}",
                            fontSize = 12.sp,
                            color = Color(0xFFCBD5E1)
                        )
                    }
                },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.Default.ArrowBack,
                            contentDescription = "Back",
                            tint = Color.White
                        )
                    }
                },
                actions = {
                    IconButton(onClick = onViewMap) {
                        Icon(
                            imageVector = Icons.Default.Map,
                            contentDescription = "View Map",
                            tint = Color.White
                        )
                    }
                    IconButton(onClick = onRefresh) {
                        Icon(
                            imageVector = Icons.Default.LocationOn,
                            contentDescription = "Refresh",
                            tint = Color.White
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(containerColor = KpfcNavyPrimary)
            )
        },
        bottomBar = {
            Surface(
                modifier = Modifier.fillMaxWidth(),
                shadowElevation = 8.dp,
                color = Color.White
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    OutlinedButton(
                        onClick = onRequestReturnToBase,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = KpfcRedDanger),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Icon(Icons.Default.Warning, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(text = "Return to Base", fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                    }

                    Button(
                        onClick = onCompleteTrip,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.buttonColors(containerColor = KpfcGreenSuccess),
                        shape = RoundedCornerShape(10.dp)
                    ) {
                        Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(text = "Complete Trip", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }
    ) { innerPadding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Text(
                    text = "Ordered Delivery Sequence",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = KpfcNavyDark
                )
                Text(
                    text = "Stops must be confirmed sequentially as visited.",
                    fontSize = 12.sp,
                    color = Color.Gray
                )
                Spacer(modifier = Modifier.height(4.dp))
            }

            itemsIndexed(trip.stops) { index, stop ->
                // Check sequential status: can this stop be arrived at?
                val priorStopsCompleted = trip.stops
                    .take(index)
                    .all { it.status == "completed" || it.status == "arrived" }

                StopCard(
                    stop = stop,
                    isSequenceAllowed = priorStopsCompleted,
                    onArrive = {
                        if (priorStopsCompleted) {
                            onMarkStopArrived(stop.id)
                        } else {
                            Toast.makeText(
                                context,
                                "Sequential violation: Complete previous stops first!",
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                    },
                    onNavigate = {
                        val lat = stop.latitude ?: -1.286389
                        val lng = stop.longitude ?: 36.817223
                        val uri = Uri.parse("google.navigation:q=$lat,$lng")
                        val intent = Intent(Intent.ACTION_VIEW, uri)
                        intent.setPackage("com.google.android.apps.maps")
                        try {
                            context.startActivity(intent)
                        } catch (e: Exception) {
                            Toast.makeText(context, "Navigating to: ${stop.locationName}", Toast.LENGTH_SHORT).show()
                        }
                    },
                    onCall = {
                        stop.contactPhone?.let { phone ->
                            val intent = Intent(Intent.ACTION_DIAL, Uri.parse("tel:$phone"))
                            try {
                                context.startActivity(intent)
                            } catch (e: Exception) {
                                Toast.makeText(context, "Contact: $phone", Toast.LENGTH_SHORT).show()
                            }
                        }
                    }
                )
            }
        }
    }
}

@Composable
fun StopCard(
    stop: TripStopDto,
    isSequenceAllowed: Boolean,
    onArrive: () -> Unit,
    onNavigate: () -> Unit,
    onCall: () -> Unit
) {
    val isCompleted = stop.status == "completed"
    val isArrived = stop.status == "arrived"
    val isLocked = !isSequenceAllowed && !isCompleted && !isArrived

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(
            containerColor = when {
                isCompleted -> Color(0xFFF8FAFC)
                isArrived -> Color(0xFFF0FDF4)
                isLocked -> Color(0xFFF1F5F9)
                else -> Color.White
            }
        ),
        border = if (isArrived) androidx.compose.foundation.BorderStroke(1.5.dp, KpfcGreenSuccess) else null,
        elevation = CardDefaults.cardElevation(defaultElevation = if (isLocked) 0.dp else 2.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(28.dp)
                            .background(
                                color = when {
                                    isCompleted -> KpfcGreenSuccess
                                    isArrived -> KpfcGreenSuccess
                                    isLocked -> Color.Gray
                                    else -> KpfcNavyPrimary
                                },
                                shape = CircleShape
                            ),
                        contentAlignment = Alignment.Center
                    ) {
                        if (isCompleted || isArrived) {
                            Icon(
                                imageVector = Icons.Default.Check,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(16.dp)
                            )
                        } else if (isLocked) {
                            Icon(
                                imageVector = Icons.Default.Lock,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(14.dp)
                            )
                        } else {
                            Text(
                                text = stop.sequence.toString(),
                                color = Color.White,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }

                    Spacer(modifier = Modifier.width(10.dp))

                    Column {
                        Text(
                            text = stop.locationName,
                            fontSize = 15.sp,
                            fontWeight = FontWeight.Bold,
                            color = if (isLocked) Color.Gray else KpfcNavyDark
                        )
                        Text(
                            text = "Stop Type: ${stop.stopType.uppercase()}",
                            fontSize = 11.sp,
                            color = Color.Gray
                        )
                    }
                }

                Box(
                    modifier = Modifier
                        .background(
                            color = when {
                                isCompleted -> Color(0xFFDCFCE7)
                                isArrived -> Color(0xFFDCFCE7)
                                isLocked -> Color(0xFFE2E8F0)
                                else -> KpfcNavyLight
                            },
                            shape = RoundedCornerShape(6.dp)
                        )
                        .padding(horizontal = 8.dp, vertical = 2.dp)
                ) {
                    Text(
                        text = when {
                            isCompleted -> "Completed"
                            isArrived -> "Arrived"
                            isLocked -> "Locked"
                            else -> "Pending"
                        },
                        fontSize = 11.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = when {
                            isCompleted || isArrived -> KpfcGreenSuccess
                            isLocked -> Color.Gray
                            else -> KpfcNavyPrimary
                        }
                    )
                }
            }

            if (!stop.address.isNullOrBlank()) {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = stop.address,
                    fontSize = 13.sp,
                    color = Color.DarkGray
                )
            }

            if (!stop.deliveryInstructions.isNullOrBlank() && !isLocked) {
                Spacer(modifier = Modifier.height(6.dp))
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(Color(0xFFFEF9C3), RoundedCornerShape(6.dp))
                        .padding(8.dp)
                ) {
                    Text(
                        text = "Note: ${stop.deliveryInstructions}",
                        fontSize = 12.sp,
                        color = Color(0xFF854D0E)
                    )
                }
            }

            // Interactive Actions if active
            if (!isCompleted && !isLocked) {
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedButton(
                        onClick = onNavigate,
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Icon(Icons.Default.LocationOn, contentDescription = null, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(4.dp))
                        Text("Navigate", fontSize = 12.sp)
                    }

                    if (stop.contactPhone != null) {
                        OutlinedButton(
                            onClick = onCall,
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Icon(Icons.Default.Phone, contentDescription = null, modifier = Modifier.size(16.dp))
                        }
                    }

                    Button(
                        onClick = onArrive,
                        modifier = Modifier.weight(1.3f),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = if (isArrived) KpfcGreenSuccess else KpfcNavyPrimary
                        ),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Text(
                            text = if (isArrived) "✓ Arrived" else "I Have Arrived",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }
}
