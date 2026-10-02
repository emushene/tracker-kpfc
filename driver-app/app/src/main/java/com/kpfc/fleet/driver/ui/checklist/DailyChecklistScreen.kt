package com.kpfc.fleet.driver.ui.checklist

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.kpfc.fleet.driver.data.model.ChecklistTemplateFieldDto
import com.kpfc.fleet.driver.data.model.DriverChecklistItemAnswer
import com.kpfc.fleet.driver.data.model.DriverDailyChecklistDto
import com.kpfc.fleet.driver.data.model.DriverDailyChecklistSubmissionRequest
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun DailyChecklistScreen(
    driverName: String,
    checklist: DriverDailyChecklistDto?,
    isLoading: Boolean,
    onBack: () -> Unit,
    onSubmit: (DriverDailyChecklistSubmissionRequest) -> Unit
) {
    val template = checklist?.template
    val itemResults = remember(template?.id) { mutableStateMapOf<String, String>() }
    val reportValues = remember(template?.id) { mutableStateMapOf<String, String>() }
    var odometer by remember(template?.id) { mutableStateOf("") }

    Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        if (template == null) {
            Column(
                modifier = Modifier.fillMaxSize().padding(24.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                if (isLoading) CircularProgressIndicator()
                Text(if (isLoading) "Loading daily checklist" else "Daily checklist is unavailable")
                Spacer(Modifier.height(12.dp))
                OutlinedButton(onClick = onBack) { Text("Back") }
            }
            return@Surface
        }

        val assignedVehicle = checklist.assignedVehicle
        val requiredItemsComplete = template.checklistItems
            .filter { it.required }
            .all { itemResults[it.itemKey] in listOf("pass", "fail", "na") }
        val signature = reportValues["driver_signature"].orEmpty()
        val canSubmit = assignedVehicle != null && odometer.toIntOrNull() != null && requiredItemsComplete && signature.isNotBlank() && !isLoading

        LazyColumn(
            modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Column(modifier = Modifier.padding(top = 16.dp)) {
                    OutlinedButton(onClick = onBack, shape = RoundedCornerShape(8.dp)) { Text("Back to dashboard") }
                    Text(template.name, modifier = Modifier.padding(top = 12.dp), fontSize = 20.sp, fontWeight = FontWeight.Bold)
                    Text("${assignedVehicle?.plateNumber ?: "No vehicle assigned"} · $driverName", fontSize = 13.sp)
                    Text(template.frequency ?: "Before the first trip", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }

            item {
                OutlinedTextField(
                    value = odometer,
                    onValueChange = { value -> odometer = value.filter(Char::isDigit) },
                    modifier = Modifier.fillMaxWidth(),
                    label = { Text("Odometer (km)") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true
                )
            }

            template.checklistItems.groupBy { it.sectionTitle ?: "Daily checks" }.forEach { (sectionTitle, sectionItems) ->
                item(key = "section-$sectionTitle") {
                    Text(sectionTitle, modifier = Modifier.padding(top = 8.dp), fontWeight = FontWeight.SemiBold)
                }
                items(sectionItems, key = { it.itemKey }) { checklistItem ->
                    Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                        Column(modifier = Modifier.fillMaxWidth().padding(12.dp)) {
                            Text(checklistItem.label, fontWeight = FontWeight.Medium)
                            checklistItem.description?.takeIf(String::isNotBlank)?.let {
                                Text(it, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                listOf("pass" to "OK", "fail" to "Issue", "na" to "N/A").forEach { (value, label) ->
                                    Row(verticalAlignment = Alignment.CenterVertically) {
                                        RadioButton(
                                            selected = itemResults[checklistItem.itemKey] == value,
                                            onClick = { itemResults[checklistItem.itemKey] = value }
                                        )
                                        Text(label, fontSize = 12.sp)
                                    }
                                }
                            }
                        }
                    }
                }
            }

            template.fields.filter { it.fieldGroup == "report" || it.fieldKey == "route" }.forEach { field ->
                item(key = "field-${field.fieldKey}") {
                    ChecklistReportField(
                        field = field,
                        value = reportValues[field.fieldKey].orEmpty(),
                        onValueChange = { reportValues[field.fieldKey] = it }
                    )
                }
            }

            item {
                Button(
                    onClick = {
                        val answers = template.checklistItems.map { item ->
                            DriverChecklistItemAnswer(
                                itemKey = item.itemKey,
                                result = itemResults[item.itemKey] ?: "na"
                            )
                        }
                        onSubmit(
                            DriverDailyChecklistSubmissionRequest(
                                vehicleId = assignedVehicle!!.id,
                                odometer = odometer.toInt(),
                                submissionDate = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date()),
                                items = answers,
                                fields = reportValues.toMap()
                            )
                        )
                    },
                    enabled = canSubmit,
                    modifier = Modifier.fillMaxWidth().padding(bottom = 24.dp),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text(if (isLoading) "Submitting" else "Submit daily report")
                }
            }
        }
    }
}

@Composable
private fun ChecklistReportField(
    field: ChecklistTemplateFieldDto,
    value: String,
    onValueChange: (String) -> Unit
) {
    if (field.fieldType == "select") {
        Column {
            Text(field.label, fontWeight = FontWeight.Medium)
            field.options.forEach { option ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RadioButton(selected = value == option.optionValue, onClick = { onValueChange(option.optionValue) })
                    Text(option.label, fontSize = 13.sp)
                }
            }
        }
    } else {
        OutlinedTextField(
            value = value,
            onValueChange = onValueChange,
            modifier = Modifier.fillMaxWidth(),
            label = { Text(field.label) },
            minLines = if (field.fieldType == "textarea") 2 else 1,
            keyboardOptions = KeyboardOptions(
                keyboardType = if (field.fieldType == "number") KeyboardType.Number else KeyboardType.Text
            )
        )
    }
}