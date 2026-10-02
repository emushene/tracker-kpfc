<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeploymentRequest;
use App\Http\Resources\VehicleDeploymentResource;
use App\Models\Vehicle;
use App\Models\VehicleDeployment;
use App\Services\VehicleDeploymentService;
use App\Services\VehicleRouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeploymentController extends Controller
{
    /**
     * Dispatch or assign a vehicle on a temporary mission to a shop or client location.
     *
     * Once dispatched, the deployment becomes the primary routing destination,
     * overriding the permanent home shop for distance and ETA calculations.
     */
    public function store(
        StoreDeploymentRequest $request,
        Vehicle $vehicle,
        VehicleDeploymentService $deploymentService,
        VehicleRouteService $routeService
    ): JsonResponse {
        $destinationType = (string) $request->input('destination_type');
        $destinationId = (int) $request->input('destination_id');
        $purpose = $request->input('purpose');
        $notes = $request->input('notes');
        $driverId = $request->input('driver_external_user_id');
        $driverName = $request->input('driver_name');
        $driverPhone = $request->input('driver_phone');
        $journeyState = $request->input('journey_state', 'going');

        // Status defaults to 'dispatched' to satisfy immediate dispatch requirements
        $requestedStatus = $request->input('status', 'dispatched');

        // Step 1: Create the new deployment in 'planned' status
        $deployment = $deploymentService->create(
            vehicle: $vehicle,
            destinationType: $destinationType,
            destinationId: $destinationId,
            purpose: $purpose,
            notes: $notes,
            driverExternalUserId: $driverId,
            driverName: $driverName,
            driverPhone: $driverPhone,
            journeyState: $journeyState
        );

        // Step 2: If dispatched status is requested, transition the deployment immediately
        if ($requestedStatus === 'dispatched') {
            $deployment = $deploymentService->dispatch($deployment);
        }

        // Step 3: Trigger route calculation towards the deployment destination
        try {
            $routeService->updateRoute($vehicle->fresh());
        } catch (\Throwable $e) {
            Log::warning('OSRM route calculation failed after deployment creation.', [
                'vehicle_id' => $vehicle->id,
                'deployment_id' => $deployment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Return the created deployment resource with destination loaded
        $deployment->load(['destination', 'vehicle']);

        return response()->json([
            'message' => $requestedStatus === 'dispatched'
                ? 'Vehicle successfully dispatched on deployment.'
                : 'Vehicle deployment successfully planned.',
            'deployment' => new VehicleDeploymentResource($deployment),
        ], 201);
    }

    public function release(
        Vehicle $vehicle,
        int $deployment,
        VehicleDeploymentService $deploymentService,
        VehicleRouteService $routeService
    ): JsonResponse {
        $deploymentModel = $this->deploymentForVehicle($vehicle, $deployment);

        try {
            $deploymentModel = $deploymentService->complete($deploymentModel);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->recalculateRoute($vehicle, $routeService, $deploymentModel->id);

        return response()->json([
            'message' => 'Vehicle deployment released successfully.',
            'deployment' => new VehicleDeploymentResource($deploymentModel->load('destination')),
        ]);
    }

    public function cancel(
        Vehicle $vehicle,
        int $deployment,
        VehicleDeploymentService $deploymentService,
        VehicleRouteService $routeService
    ): JsonResponse {
        $deploymentModel = $this->deploymentForVehicle($vehicle, $deployment);

        try {
            $deploymentModel = $deploymentService->cancel($deploymentModel);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->recalculateRoute($vehicle, $routeService, $deploymentModel->id);

        return response()->json([
            'message' => 'Vehicle deployment cancelled successfully.',
            'deployment' => new VehicleDeploymentResource($deploymentModel->load('destination')),
        ]);
    }

    /**
     * Update the driver's journey state on an active deployment (going, at_stop, going_back, at_base).
     */
    public function updateJourneyState(
        Request $request,
        Vehicle $vehicle,
        int $deployment,
        VehicleDeploymentService $deploymentService,
        VehicleRouteService $routeService
    ): JsonResponse {
        $validated = $request->validate([
            'journey_state' => ['required', 'string', 'in:going,at_stop,going_back,at_base'],
        ]);

        $deploymentModel = $this->deploymentForVehicle($vehicle, $deployment);

        try {
            $deploymentModel = $deploymentService->updateJourneyState(
                $deploymentModel,
                $validated['journey_state']
            );
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->recalculateRoute($vehicle, $routeService, $deploymentModel->id);

        return response()->json([
            'message' => "Deployment journey state updated to {$deploymentModel->journey_state}.",
            'deployment' => new VehicleDeploymentResource($deploymentModel->load(['destination', 'vehicle'])),
        ]);
    }

    private function deploymentForVehicle(Vehicle $vehicle, int $deployment): VehicleDeployment
    {
        return $vehicle->deployments()->whereKey($deployment)->firstOrFail();
    }

    private function recalculateRoute(
        Vehicle $vehicle,
        VehicleRouteService $routeService,
        int $deploymentId
    ): void {
        try {
            $routeService->updateRoute($vehicle->fresh());
        } catch (\Throwable $exception) {
            Log::warning('OSRM route calculation failed after deployment status change.', [
                'vehicle_id' => $vehicle->id,
                'deployment_id' => $deploymentId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
