package com.kpfc.fleet.driver.data.db

import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.data.model.VehicleDto

// ─── DTO → Entity ──────────────────────────────────────────────────────────

fun VehicleDto.toEntity(): VehicleEntity = VehicleEntity(
    id = id,
    plateNumber = plateNumber,
    deviceName = deviceName,
    imei = imei,
    locationName = locationName,
    latitude = latitude,
    longitude = longitude
)

fun TripDto.toEntity(): TripEntity = TripEntity(
    id = id,
    tripNumber = tripNumber,
    driverId = driverId,
    status = status,
    plannedStart = plannedStart,
    actualStart = actualStart,
    startingMileage = startingMileage,
    endingMileage = endingMileage,
    tripMileage = tripMileage,
    vehicleId = vehicle?.id
)

fun TripStopDto.toEntity(): TripStopEntity = TripStopEntity(
    id = id,
    tripId = tripId,
    sequence = sequence,
    stopType = stopType,
    locationName = locationName,
    address = address,
    latitude = latitude,
    longitude = longitude,
    contactPhone = contactPhone,
    deliveryInstructions = deliveryInstructions,
    status = status,
    arrivedAt = arrivedAt
)

// ─── Entity → DTO ──────────────────────────────────────────────────────────

fun VehicleEntity.toDto(): VehicleDto = VehicleDto(
    id = id,
    plateNumber = plateNumber,
    deviceName = deviceName,
    imei = imei,
    locationName = locationName,
    latitude = latitude,
    longitude = longitude
)

fun TripStopEntity.toDto(): TripStopDto = TripStopDto(
    id = id,
    tripId = tripId,
    sequence = sequence,
    stopType = stopType,
    locationName = locationName,
    address = address,
    latitude = latitude,
    longitude = longitude,
    contactPhone = contactPhone,
    deliveryInstructions = deliveryInstructions,
    status = status,
    arrivedAt = arrivedAt
)

fun TripEntity.toDto(stops: List<TripStopDto> = emptyList(), vehicle: VehicleDto? = null): TripDto =
    TripDto(
        id = id,
        tripNumber = tripNumber,
        driverId = driverId,
        status = status,
        plannedStart = plannedStart,
        actualStart = actualStart,
        startingMileage = startingMileage,
        endingMileage = endingMileage,
        tripMileage = tripMileage,
        vehicle = vehicle,
        stops = stops
    )
