package com.kpfc.fleet.driver.data.db

import androidx.room.Entity
import androidx.room.ForeignKey
import androidx.room.Index
import androidx.room.PrimaryKey

/**
 * Cached copy of the driver's assigned vehicle.
 * Only one row is expected per driver session.
 */
@Entity(tableName = "vehicles")
data class VehicleEntity(
    @PrimaryKey val id: Long,
    val plateNumber: String?,
    val deviceName: String?,
    val imei: String,
    val locationName: String?,
    val latitude: Double?,
    val longitude: Double?,
    val cachedAt: Long = System.currentTimeMillis()
)

/**
 * Cached trip row. Stores active + upcoming trips locally so the driver
 * can view them when offline.
 */
@Entity(tableName = "trips")
data class TripEntity(
    @PrimaryKey val id: Long,
    val tripNumber: String,
    val driverId: String,
    val status: String,
    val plannedStart: String?,
    val actualStart: String?,
    val startingMileage: Int?,
    val endingMileage: Int?,
    val tripMileage: Int?,
    val vehicleId: Long?,
    val cachedAt: Long = System.currentTimeMillis()
)

/**
 * Cached stop row for a given trip.
 */
@Entity(
    tableName = "trip_stops",
    foreignKeys = [
        ForeignKey(
            entity = TripEntity::class,
            parentColumns = ["id"],
            childColumns = ["tripId"],
            onDelete = ForeignKey.CASCADE
        )
    ],
    indices = [Index("tripId")]
)
data class TripStopEntity(
    @PrimaryKey val id: Long,
    val tripId: Long,
    val sequence: Int,
    val stopType: String,
    val locationName: String,
    val address: String?,
    val latitude: Double?,
    val longitude: Double?,
    val contactPhone: String?,
    val deliveryInstructions: String?,
    val status: String,
    val arrivedAt: String?,
    val cachedAt: Long = System.currentTimeMillis()
)
