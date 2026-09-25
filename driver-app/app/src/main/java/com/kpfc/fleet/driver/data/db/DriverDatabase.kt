package com.kpfc.fleet.driver.data.db

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase

@Database(
    entities = [VehicleEntity::class, TripEntity::class, TripStopEntity::class],
    version = 1,
    exportSchema = false
)
abstract class DriverDatabase : RoomDatabase() {
    abstract fun vehicleDao(): VehicleDao
    abstract fun tripDao(): TripDao
    abstract fun tripStopDao(): TripStopDao

    companion object {
        @Volatile
        private var INSTANCE: DriverDatabase? = null

        fun getInstance(context: Context): DriverDatabase {
            return INSTANCE ?: synchronized(this) {
                Room.databaseBuilder(
                    context.applicationContext,
                    DriverDatabase::class.java,
                    "driver_cache.db"
                )
                    .fallbackToDestructiveMigration()
                    .build()
                    .also { INSTANCE = it }
            }
        }
    }
}
