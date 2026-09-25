package com.kpfc.fleet.driver.ui.trip

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.ui.theme.KpfcNavyDark
import com.kpfc.fleet.driver.ui.theme.KpfcRedDanger
import com.kpfc.fleet.driver.ui.theme.KpfcRedLight

@Composable
fun ReturnToBaseDialog(
    undeliveredStops: List<TripStopDto>,
    onDismiss: () -> Unit,
    onSubmit: (reason: String, notes: String) -> Unit
) {
    val reasons = listOf(
        "Vehicle Mechanical Failure / Breakdown",
        "Severe Road Obstruction or Accident",
        "Destination Branch Closed / Cannot Receive",
        "Medical Emergency / Driver Safety",
        "Other Operational Issue"
    )

    var selectedReason by remember { mutableStateOf(reasons[0]) }
    var notes by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.Warning,
                    contentDescription = null,
                    tint = KpfcRedDanger,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Request Return to Base",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = KpfcNavyDark
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(KpfcRedLight, RoundedCornerShape(8.dp))
                        .padding(10.dp)
                ) {
                    Text(
                        text = "This request will be sent to the Fleet Manager. ${undeliveredStops.size} undelivered stop(s) will be returned.",
                        fontSize = 12.sp,
                        color = KpfcRedDanger,
                        lineHeight = 16.sp
                    )
                }

                Text(
                    text = "Select Reason:",
                    fontSize = 13.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = KpfcNavyDark
                )

                reasons.forEach { reason ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable { selectedReason = reason },
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        RadioButton(
                            selected = (reason == selectedReason),
                            onClick = { selectedReason = reason },
                            colors = RadioButtonDefaults.colors(selectedColor = KpfcRedDanger)
                        )
                        Text(
                            text = reason,
                            fontSize = 13.sp,
                            color = Color.DarkGray
                        )
                    }
                }

                OutlinedTextField(
                    value = notes,
                    onValueChange = { notes = it },
                    label = { Text("Additional Remarks") },
                    placeholder = { Text("Details of incident or location...") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(8.dp)
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    val fullReason = if (notes.isNotBlank()) "$selectedReason: $notes" else selectedReason
                    onSubmit(fullReason, notes)
                },
                colors = ButtonDefaults.buttonColors(containerColor = KpfcRedDanger),
                shape = RoundedCornerShape(8.dp)
            ) {
                Text("Submit Request", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            OutlinedButton(
                onClick = onDismiss,
                shape = RoundedCornerShape(8.dp)
            ) {
                Text("Cancel")
            }
        }
    )
}
