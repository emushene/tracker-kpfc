package com.kpfc.fleet.driver.data.api

import com.kpfc.fleet.driver.data.model.ApiResponse
import com.kpfc.fleet.driver.data.model.CompleteTripRequest
import com.kpfc.fleet.driver.data.model.LoginRequest
import com.kpfc.fleet.driver.data.model.ReturnToBaseRequestDto
import com.kpfc.fleet.driver.data.model.StartTripRequest
import com.kpfc.fleet.driver.data.model.TripDto
import com.kpfc.fleet.driver.data.model.TripStopDto
import com.kpfc.fleet.driver.data.model.UserDto
import com.kpfc.fleet.driver.data.model.VehicleDto
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path

interface DriverApiService {

    @POST("api/driver/login")
    suspend fun login(
        @Body request: LoginRequest
    ): Response<ApiResponse<UserDto>>

    @POST("api/driver/logout")
    suspend fun logout(): Response<ApiResponse<Unit>>

    @GET("api/driver/me")
    suspend fun getMe(): Response<ApiResponse<UserDto>>

    @GET("api/driver/trips/active")
    suspend fun getActiveTrip(): Response<ApiResponse<TripDto>>

    @GET("api/driver/trips/upcoming")
    suspend fun getUpcomingTrips(): Response<ApiResponse<List<TripDto>>>

    @GET("api/driver/assigned-vehicle")
    suspend fun getAssignedVehicle(): Response<ApiResponse<VehicleDto>>

    @POST("api/driver/trips/{tripId}/start")
    suspend fun startTrip(
        @Path("tripId") tripId: Long,
        @Body request: StartTripRequest
    ): Response<ApiResponse<TripDto>>

    @GET("api/driver/trips/{tripId}/stops")
    suspend fun getTripStops(
        @Path("tripId") tripId: Long
    ): Response<ApiResponse<List<TripStopDto>>>

    @POST("api/driver/stops/{stopId}/arrive")
    suspend fun arriveStop(
        @Path("stopId") stopId: Long
    ): Response<ApiResponse<TripStopDto>>

    @POST("api/driver/trips/{tripId}/return-to-base")
    suspend fun returnToBase(
        @Path("tripId") tripId: Long,
        @Body request: ReturnToBaseRequestDto
    ): Response<ApiResponse<Any>>

    @POST("api/driver/trips/{tripId}/complete")
    suspend fun completeTrip(
        @Path("tripId") tripId: Long,
        @Body request: CompleteTripRequest
    ): Response<ApiResponse<TripDto>>
}
