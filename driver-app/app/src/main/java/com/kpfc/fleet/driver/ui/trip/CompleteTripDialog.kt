package com.kpfc.fleet.driver.ui.trip

import androidx.compose.foundation.background
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
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
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
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.kpfc.fleet.driver.ui.theme.KpfcGreenLight
import com.kpfc.fleet.driver.ui.theme.KpfcGreenSuccess
import com.kpfc.fleet.driver.ui.theme.KpfcNavyDark
import com.kpfc.fleet.driver.ui.theme.KpfcRedDanger

@Composable
fun CompleteTripDialog(
    startingMileage: Int,
    onDismiss: () -> Unit,
    onConfirm: (endingMileage: Int) -> Unit
) {
    var endingMileageText by remember { mutableStateOf((startingMileage + 45).toString()) }
    val endingMileage = endingMileageText.toIntOrNull() ?: startingMileage
    val tripDistance = (endingMileage - startingMileage).coerceAtLeast(0)
    val isValid = endingMileage >= startingMileage

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.Check,
                    contentDescription = null,
                    tint = KpfcGreenSuccess,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Complete Trip & Record Mileage",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = KpfcNavyDark
                )
            }
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(
                    text = "Please record your vehicle's final odometer reading upon arrival at the central depot.",
                    fontSize = 13.sp,
                    color = Color.DarkGray
                )

                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(text = "Starting Odometer:", fontSize = 13.sp, color = Color.Gray)
                    Text(
                        text = "$startingMileage km",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = KpfcNavyDark
                    )
                }

                OutlinedTextField(
                    value = endingMileageText,
                    onValueChange = { endingMileageText = it },
                    label = { Text("Final Odometer Reading (km)") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    isError = !isValid,
                    shape = RoundedCornerShape(8.dp)
                )

                if (!isValid) {
                    Text(
                        text = "Ending mileage must be greater than or equal to starting mileage ($startingMileage km)",
                        fontSize = 11.sp,
                        color = KpfcRedDanger
                    )
                }

                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(KpfcGreenLight, RoundedCornerShape(8.dp))
                        .padding(12.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Total Trip Mileage:",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = KpfcNavyDark
                        )
                        Text(
                            text = "+$tripDistance km",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = KpfcGreenSuccess
                        )
                    }
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    if (isValid) {
                        onConfirm(endingMileage)
                    }
                },
                enabled = isValid,
                colors = ButtonDefaults.buttonColors(containerColor = KpfcGreenSuccess),
                shape = RoundedCornerShape(8.dp)
            ) {
                Text("Finalize & Submit", fontWeight = FontWeight.Bold)
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
