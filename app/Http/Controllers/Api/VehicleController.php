<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignShopRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleLocationRequest;
use App\Http\Resources\VehicleLocationResource;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\ShopLocationService;
use App\Services\VehicleRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    /**
     * List all fleet vehicles with their live locations, status, assigned home shop, and active deployments.
     *
     * Supports filtering by:
     * - ?search=... (matches plate_number, imei, or device_name)
     * - ?shop_id=... (filters by assigned home shop)
     * - ?active=1/0 (filters active/inactive status)
     * - ?all=1 (returns full unpaginated collection; otherwise paginates by 25 per page)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Vehicle::query()
            // Eager load relations to prevent N+1 query bottlenecks
            ->with([
                'assignedShop',
                'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
                'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
            ]);

        // Filter by assigned home shop
        if ($request->filled('shop_id')) {
            $query->where('assigned_shop_id', $request->integer('shop_id'));
        }

        // Filter by active status if explicitly provided
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Search query across plate number, IMEI, and device name
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('plate_number', 'like', "%{$term}%")
                    ->orWhere('imei', 'like', "%{$term}%")
                    ->orWhere('device_name', 'like', "%{$term}%");
            });
        }

        // Order by plate number or ID
        $query->orderBy('plate_number')->orderBy('id');

        // Allow fetching all records or paginating (default 25 per page)
        $vehicles = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->integer('per_page', 25));

        return VehicleResource::collection($vehicles);
    }

    /**
     * Display a single vehicle with complete telematics and assignment details.
     */
    public function show(Vehicle $vehicle): VehicleResource
    {
        $vehicle->load([
            'assignedShop',
            'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
            'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
        ]);

        return new VehicleResource($vehicle);
    }

    /**
     * Assign or update a vehicle's permanent home shop.
     *
     * Once assigned, the vehicle's road distance and duration to the home shop
     * are automatically recalculated via OSRM if valid GPS positions exist.
     */
    public function assignShop(
        AssignShopRequest $request,
        Vehicle $vehicle,
        VehicleRouteService $routeService
    ): JsonResponse {
        $shopId = $request->input('shop_id');

        // Update the vehicle's permanent assigned shop
        $vehicle->update([
            'assigned_shop_id' => $shopId,
        ]);

        // Trigger immediate OSRM route recalculation if vehicle has GPS data
        try {
            $routeService->updateRoute($vehicle->fresh());
        } catch (\Throwable $e) {
            Log::warning('OSRM route calculation failed after shop assignment.', [
                'vehicle_id' => $vehicle->id,
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
        }

        // Reload relationships for a comprehensive resource response
        $vehicle->load([
            'assignedShop',
            'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
            'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
        ]);

        return response()->json([
            'message' => $shopId
                ? 'Vehicle home shop successfully assigned.'
                : 'Vehicle home shop assignment cleared.',
            'vehicle' => new VehicleResource($vehicle),
        ]);
    }

    /**
     * Create a new vehicle record for fleet tracking and integration.
     */
    public function store(
        StoreVehicleRequest $request,
        VehicleRouteService $routeService
    ): JsonResponse {
        $validated = $request->validated();

        $vehicle = Vehicle::create($validated);

        if ($vehicle->assigned_shop_id !== null && $vehicle->location_latitude !== null && $vehicle->location_longitude !== null) {
            try {
                $routeService->updateRoute($vehicle->fresh());
            } catch (\Throwable $e) {
                Log::warning('OSRM route calculation failed after vehicle creation.', [
                    'vehicle_id' => $vehicle->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $vehicle->load([
            'assignedShop',
            'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
            'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
        ]);

        return response()->json([
            'message' => 'Vehicle successfully created.',
            'vehicle' => new VehicleResource($vehicle),
        ], 201);
    }

    /**
     * Provide the live location, movement status, and telemetry details for a single vehicle.
     */
    public function location(Vehicle $vehicle): VehicleLocationResource
    {
        $vehicle->load([
            'assignedShop',
            'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
            'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
        ]);

        return new VehicleLocationResource($vehicle);
    }

    /**
     * Query vehicle location by query parameters (e.g. ?plate_number=... or ?imei=... or ?id=...).
     */
    public function queryLocation(Request $request): VehicleLocationResource|JsonResponse
    {
        $query = Vehicle::query();

        if ($request->filled('plate_number')) {
            $plate = (string) $request->input('plate_number');
            $normalizedPlate = str_replace([' ', '-'], '', $plate);
            $query->where(function ($q) use ($plate, $normalizedPlate): void {
                $q->where('plate_number', $plate)
                    ->orWhereRaw("REPLACE(REPLACE(plate_number, ' ', ''), '-', '') = ?", [$normalizedPlate]);
            });
        } elseif ($request->filled('imei')) {
            $query->where('imei', (string) $request->input('imei'));
        } elseif ($request->filled('id')) {
            $query->where('id', $request->integer('id'));
        } else {
            return response()->json([
                'message' => 'Please provide a plate_number, imei, or id query parameter to locate a vehicle.',
            ], 400);
        }

        $vehicle = $query->with([
            'assignedShop',
            'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
            'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
        ])->first();

        if (! $vehicle) {
            return response()->json([
                'message' => 'Vehicle not found.',
            ], 404);
        }

        return new VehicleLocationResource($vehicle);
    }

    /**
     * Ingest or update a vehicle's GPS position and location from external systems.
     */
    public function updateLocation(
        UpdateVehicleLocationRequest $request,
        Vehicle $vehicle,
        ShopLocationService $shopLocationService,
        VehicleRouteService $routeService
    ): JsonResponse {
        $validated = $request->validated();

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        // Determine location name: user-supplied or resolved via shop geofence
        $locationName = $validated['location_name'] ?? null;
        if (empty($locationName)) {
            $nearbyShop = $shopLocationService->findNearbyShop($latitude, $longitude);
            if ($nearbyShop) {
                $locationName = $nearbyShop->name;
            } else {
                $locationName = $vehicle->location_name;
            }
        }

        $gpsTime = isset($validated['gps_time'])
            ? (int) $validated['gps_time']
            : (isset($validated['recorded_at']) ? strtotime((string) $validated['recorded_at']) : now()->timestamp);

        // Record a telemetry position entry
        $vehicle->positions()->create([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $validated['speed'] ?? null,
            'course' => $validated['course'] ?? null,
            'battery' => $validated['battery'] ?? null,
            'acc_status' => $validated['acc_status'] ?? ($request->has('ignition_on') ? ($request->boolean('ignition_on') ? 1 : 0) : null),
            'odometer' => $validated['odometer'] ?? null,
            'mileage' => $validated['mileage'] ?? null,
            'gps_time' => $gpsTime,
        ]);

        // Update vehicle snapshot fields
        $vehicle->update([
            'location_name' => $locationName,
            'location_latitude' => $latitude,
            'location_longitude' => $longitude,
            'location_updated_at' => now(),
            'last_position_at' => now(),
            'online_at' => now(),
        ]);

        // Trigger route recalculation if vehicle is dispatched or assigned to a home shop
        try {
            $routeService->updateRoute($vehicle->fresh());
        } catch (\Throwable $e) {
            Log::warning('OSRM route calculation failed after vehicle location update.', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage(),
            ]);
        }

        $vehicle->load([
            'assignedShop',
            'deployments' => fn ($q) => $q->whereIn('status', ['planned', 'dispatched', 'in_progress'])->with('destination'),
            'positions' => fn ($q) => $q->latest('gps_time')->limit(1),
        ]);

        return response()->json([
            'message' => 'Vehicle location updated successfully.',
            'vehicle' => new VehicleLocationResource($vehicle),
        ]);
    }
}
