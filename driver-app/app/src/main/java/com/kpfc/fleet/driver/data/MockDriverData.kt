package com.kpfc.fleet.driver.data

import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.data.model.VehicleDto

object MockDriverData {

    val sampleVehicle = VehicleDto(
        id = 101L,
        plateNumber = "KDG 456Z",
        deviceName = "Van 04 - Nairobi Branch",
        imei = "864201948201948",
        locationName = "Nairobi Central Depot",
        latitude = -1.286389,
        longitude = 36.817223
    )

    fun createInitialStops(): List<TripStopDto> = listOf(
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
        ),
        TripStopDto(
            id = 3L,
            tripId = 501L,
            sequence = 3,
            stopType = "delivery",
            locationName = "KPFC Parklands Clinic Branch",
            address = "Parklands 3rd Avenue",
            latitude = -1.2580,
            longitude = 36.8190,
            contactPhone = "+254 722 555 666",
            deliveryInstructions = "Call receiving manager on approach.",
            status = "pending",
            arrivedAt = null
        ),
        TripStopDto(
            id = 4L,
            tripId = 501L,
            sequence = 4,
            stopType = "return",
            locationName = "Nairobi Central Depot",
            address = "Industrial Area, Commercial St.",
            latitude = -1.3000,
            longitude = 36.8300,
            contactPhone = "+254 700 111 222",
            deliveryInstructions = "Park in Bay 4. Hand in delivery confirmation slips.",
            status = "pending",
            arrivedAt = null
        )
    )

    fun createSampleActiveTrip(): TripDto = TripDto(
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
        stops = createInitialStops()
    )

    fun createSampleUpcomingTrips(): List<TripDto> = listOf(
        TripDto(
            id = 502L,
            tripNumber = "TRP-2026-0045",
            driverId = "drv-9001",
            status = "planned",
            plannedStart = "Tomorrow at 07:30 AM",
            actualStart = null,
            startingMileage = null,
            endingMileage = null,
            tripMileage = null,
            vehicle = sampleVehicle,
            stops = emptyList()
        ),
        TripDto(
            id = 503L,
            tripNumber = "TRP-2026-0049",
            driverId = "drv-9001",
            status = "planned",
            plannedStart = "28 Sep 2026 at 09:00 AM",
            actualStart = null,
            startingMileage = null,
            endingMileage = null,
            tripMileage = null,
            vehicle = sampleVehicle,
            stops = emptyList()
        )
    )
}
