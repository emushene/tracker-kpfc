<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\VehicleMileage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverTripController extends Controller
{
    /**
     * Resolve the external user ID for the driver.
     */
    protected function resolveDriverId(Request $request): string
    {
        if ($request->filled('driver_id') && ($request->user()?->canWrite() || app()->environment('testing', 'local'))) {
            return (string) $request->input('driver_id');
        }

        $user = $request->user();

        return (string) ($user?->kpfc_sub ?? $user?->id ?? '');
    }

    /**
     * Get the active trip for the authenticated driver.
     */
    public function activeTrip(Request $request): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        $trip = Trip::query()
            ->where('driver_external_user_id', $driverId)
            ->active()
            ->with(['vehicle.assignedShop', 'stops', 'activeReturnRequest'])
            ->latest('actual_start')
            ->first();

        return response()->json([
            'data' => $trip,
            'message' => $trip ? 'Active trip retrieved successfully.' : 'No active trip found.',
        ]);
    }

    /**
     * Get upcoming trips scheduled for the authenticated driver.
     */
    public function upcomingTrips(Request $request): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        $trips = Trip::query()
            ->where('driver_external_user_id', $driverId)
            ->upcoming()
            ->with(['vehicle.assignedShop', 'stops'])
            ->get();

        return response()->json([
            'data' => $trips,
        ]);
    }

    /**
     * Get the vehicle assigned to the driver (active trip first, otherwise next planned trip).
     */
    public function assignedVehicle(Request $request): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        $activeTrip = Trip::query()
            ->where('driver_external_user_id', $driverId)
            ->active()
            ->with('vehicle.assignedShop')
            ->first();

        if ($activeTrip && $activeTrip->vehicle) {
            return response()->json([
                'data' => $activeTrip->vehicle,
                'trip_id' => $activeTrip->id,
                'status' => 'active_trip',
            ]);
        }

        $upcomingTrip = Trip::query()
            ->where('driver_external_user_id', $driverId)
            ->upcoming()
            ->with('vehicle.assignedShop')
            ->first();

        if ($upcomingTrip && $upcomingTrip->vehicle) {
            return response()->json([
                'data' => $upcomingTrip->vehicle,
                'trip_id' => $upcomingTrip->id,
                'status' => 'upcoming_trip',
            ]);
        }

        return response()->json([
            'data' => null,
            'message' => 'No vehicle currently assigned.',
        ]);
    }

    /**
     * Manually start a scheduled trip.
     */
    public function startTrip(Request $request, Trip $trip): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        if ($trip->driver_external_user_id !== $driverId && ! $request->user()?->canWrite()) {
            return response()->json([
                'message' => 'You are not authorized to start this trip.',
            ], 403);
        }

        if ($trip->status !== 'planned') {
            return response()->json([
                'message' => "Trip cannot be started because it is already {$trip->status}.",
            ], 422);
        }

        $validated = $request->validate([
            'starting_mileage' => ['nullable', 'integer', 'min:0'],
            'start_latitude' => ['nullable', 'numeric'],
            'start_longitude' => ['nullable', 'numeric'],
            'start_location_name' => ['nullable', 'string', 'max:255'],
        ]);

        $startingMileage = $validated['starting_mileage']
            ?? $trip->starting_mileage
            ?? (int) $trip->vehicle?->mileage()->latest('date')->value('odometer')
            ?? 0;

        $trip->update([
            'status' => 'in_progress',
            'actual_start' => now(),
            'starting_mileage' => $startingMileage,
            'start_latitude' => $validated['start_latitude'] ?? $trip->start_latitude,
            'start_longitude' => $validated['start_longitude'] ?? $trip->start_longitude,
            'start_location_name' => $validated['start_location_name'] ?? $trip->start_location_name,
        ]);

        return response()->json([
            'message' => 'Trip started successfully.',
            'data' => $trip->fresh(['vehicle', 'stops']),
        ]);
    }

    /**
     * Get ordered stops for a specific trip.
     */
    public function stops(Request $request, Trip $trip): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        if ($trip->driver_external_user_id !== $driverId && ! $request->user()?->canWrite()) {
            return response()->json([
                'message' => 'You are not authorized to view stops for this trip.',
            ], 403);
        }

        return response()->json([
            'data' => $trip->stops()->orderBy('sequence')->get(),
        ]);
    }

    /**
     * Mark a stop as arrived, enforcing sequential stop execution.
     */
    public function arriveStop(Request $request, TripStop $stop): JsonResponse
    {
        $trip = $stop->trip;
        $driverId = $this->resolveDriverId($request);

        if ($trip->driver_external_user_id !== $driverId && ! $request->user()?->canWrite()) {
            return response()->json([
                'message' => 'You are not authorized to update this stop.',
            ], 403);
        }

        if (! $trip->isActive()) {
            return response()->json([
                'message' => 'Cannot arrive at stop because the trip is not active.',
            ], 422);
        }

        if ($stop->status === 'arrived') {
            return response()->json([
                'message' => 'Stop has already been marked as arrived.',
                'data' => $stop,
            ]);
        }

        // Sequential validation check
        if (! $stop->canArrive()) {
            return response()->json([
                'message' => 'Sequential stop order violation: preceding stops must be completed or marked arrived first.',
            ], 422);
        }

        $stop->update([
            'status' => 'arrived',
            'arrived_at' => now(),
        ]);

        return response()->json([
            'message' => 'Stop marked as arrived successfully.',
            'data' => $stop,
        ]);
    }

    /**
     * Submit a return-to-base request.
     */
    public function returnToBase(Request $request, Trip $trip): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        if ($trip->driver_external_user_id !== $driverId && ! $request->user()?->canWrite()) {
            return response()->json([
                'message' => 'You are not authorized to submit a return request for this trip.',
            ], 403);
        }

        if (! $trip->isActive()) {
            return response()->json([
                'message' => 'Return-to-base requests can only be submitted for active trips.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'current_location_name' => ['nullable', 'string', 'max:255'],
        ]);

        $undeliveredStops = $trip->stops()
            ->whereNotIn('status', ['arrived', 'completed', 'cancelled'])
            ->get(['id', 'sequence', 'stop_type', 'location_name', 'address'])
            ->toArray();

        $returnRequest = $trip->returnToBaseRequests()->create([
            'driver_external_user_id' => $driverId,
            'reason' => $validated['reason'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'current_location_name' => $validated['current_location_name'] ?? null,
            'undelivered_stops' => $undeliveredStops,
            'requested_at' => now(),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Return-to-base request submitted successfully.',
            'data' => $returnRequest,
        ], 201);
    }

    /**
     * Complete the trip and record final odometer mileage.
     */
    public function completeTrip(Request $request, Trip $trip): JsonResponse
    {
        $driverId = $this->resolveDriverId($request);

        if ($trip->driver_external_user_id !== $driverId && ! $request->user()?->canWrite()) {
            return response()->json([
                'message' => 'You are not authorized to complete this trip.',
            ], 403);
        }

        if (! $trip->isActive()) {
            return response()->json([
                'message' => "Trip cannot be completed from its current status ({$trip->status}).",
            ], 422);
        }

        $startingMileage = (int) ($trip->starting_mileage ?? 0);

        $validated = $request->validate([
            'ending_mileage' => ['required', 'integer', "min:{$startingMileage}"],
        ]);

        $endingMileage = (int) $validated['ending_mileage'];
        $tripMileage = $endingMileage - $startingMileage;

        $trip->update([
            'status' => 'completed',
            'actual_end' => now(),
            'ending_mileage' => $endingMileage,
            'trip_mileage' => $tripMileage,
        ]);

        // Update cumulative vehicle mileage record
        if ($trip->vehicle_id) {
            VehicleMileage::updateOrCreate(
                [
                    'vehicle_id' => $trip->vehicle_id,
                    'date' => now()->toDateString(),
                ],
                [
                    'odometer' => $endingMileage,
                ]
            );
        }

        return response()->json([
            'message' => 'Trip completed successfully.',
            'data' => $trip->fresh(['vehicle', 'stops']),
        ]);
    }
}
