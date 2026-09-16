<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignShopRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
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
}
