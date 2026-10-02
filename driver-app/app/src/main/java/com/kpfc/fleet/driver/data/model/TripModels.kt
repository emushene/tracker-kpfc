package com.kpfc.fleet.driver.data.model

import com.google.gson.annotations.SerializedName

data class ApiResponse<T>(
    @SerializedName("data") val data: T?,
    @SerializedName("message") val message: String? = null
)

data class LoginRequest(
    @SerializedName("email") val email: String,
    @SerializedName("password") val password: String
)

data class UserDto(
    @SerializedName("id") val id: Long,
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String,
    @SerializedName("role") val role: String?,
    @SerializedName("driver_id") val driverId: String
)

data class VehicleDto(
    @SerializedName("id") val id: Long,
    @SerializedName("plate_number") val plateNumber: String?,
    @SerializedName("device_name") val deviceName: String?,
    @SerializedName("imei") val imei: String,
    @SerializedName("location_name") val locationName: String?,
    @SerializedName("location_latitude") val latitude: Double?,
    @SerializedName("location_longitude") val longitude: Double?
)

data class TripStopDto(
    @SerializedName("id") val id: Long,
    @SerializedName("trip_id") val tripId: Long,
    @SerializedName("sequence") val sequence: Int,
    @SerializedName("stop_type") val stopType: String,
    @SerializedName("location_name") val locationName: String,
    @SerializedName("address") val address: String?,
    @SerializedName("latitude") val latitude: Double?,
    @SerializedName("longitude") val longitude: Double?,
    @SerializedName("contact_phone") val contactPhone: String?,
    @SerializedName("delivery_instructions") val deliveryInstructions: String?,
    @SerializedName("status") val status: String,
    @SerializedName("arrived_at") val arrivedAt: String?
)

data class TripDto(
    @SerializedName("id") val id: Long,
    @SerializedName("trip_number") val tripNumber: String,
    @SerializedName("driver_external_user_id") val driverId: String,
    @SerializedName("status") val status: String,
    @SerializedName("planned_start") val plannedStart: String?,
    @SerializedName("actual_start") val actualStart: String?,
    @SerializedName("starting_mileage") val startingMileage: Int?,
    @SerializedName("ending_mileage") val endingMileage: Int?,
    @SerializedName("trip_mileage") val tripMileage: Int?,
    @SerializedName("vehicle") val vehicle: VehicleDto?,
    @SerializedName("stops") val stops: List<TripStopDto> = emptyList()
)

data class StartTripRequest(
    @SerializedName("starting_mileage") val startingMileage: Int?,
    @SerializedName("start_latitude") val latitude: Double?,
    @SerializedName("start_longitude") val longitude: Double?,
    @SerializedName("start_location_name") val locationName: String?
)

data class ReturnToBaseRequestDto(
    @SerializedName("reason") val reason: String,
    @SerializedName("latitude") val latitude: Double?,
    @SerializedName("longitude") val longitude: Double?,
    @SerializedName("current_location_name") val locationName: String?
)

data class CompleteTripRequest(
    @SerializedName("ending_mileage") val endingMileage: Int
)

data class ChecklistItemDto(
    @SerializedName("id") val id: Long,
    @SerializedName("item_key") val itemKey: String,
    @SerializedName("section_title") val sectionTitle: String?,
    @SerializedName("sequence") val sequence: Int,
    @SerializedName("label") val label: String,
    @SerializedName("description") val description: String?,
    @SerializedName("required") val required: Boolean
)

data class ChecklistFieldOptionDto(
    @SerializedName("option_value") val optionValue: String,
    @SerializedName("label") val label: String
)

data class ChecklistTemplateFieldDto(
    @SerializedName("field_key") val fieldKey: String,
    @SerializedName("field_group") val fieldGroup: String,
    @SerializedName("label") val label: String,
    @SerializedName("field_type") val fieldType: String,
    @SerializedName("required") val required: Boolean,
    @SerializedName("options") val options: List<ChecklistFieldOptionDto> = emptyList()
)

data class ChecklistTemplateDto(
    @SerializedName("id") val id: Long,
    @SerializedName("template_key") val templateKey: String,
    @SerializedName("name") val name: String,
    @SerializedName("frequency") val frequency: String?,
    @SerializedName("checklist_items") val checklistItems: List<ChecklistItemDto> = emptyList(),
    @SerializedName("fields") val fields: List<ChecklistTemplateFieldDto> = emptyList()
)

data class DriverDailyChecklistDto(
    @SerializedName("template") val template: ChecklistTemplateDto,
    @SerializedName("assigned_vehicle") val assignedVehicle: VehicleDto?
)

data class DriverChecklistItemAnswer(
    @SerializedName("item_key") val itemKey: String,
    @SerializedName("result") val result: String,
    @SerializedName("notes") val notes: String? = null
)

data class DriverDailyChecklistSubmissionRequest(
    @SerializedName("vehicle_id") val vehicleId: Long,
    @SerializedName("odometer") val odometer: Int,
    @SerializedName("submission_date") val submissionDate: String,
    @SerializedName("items") val items: List<DriverChecklistItemAnswer>,
    @SerializedName("fields") val fields: Map<String, String>
)
