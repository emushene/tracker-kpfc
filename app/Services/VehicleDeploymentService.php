<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Models\VehicleDeployment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class VehicleDeploymentService
{
    /**
     * Deployment statuses considered active.
     */
    private const ACTIVE_STATUSES = [
        'planned',
        'dispatched',
        'in_progress',
    ];

    public function __construct(
        private OsrmService $osrm
    ) {}

    /**
     * Create a new deployment for a vehicle.
     *
     * A vehicle can only have one active deployment at a time.
     */
    public function create(
        Vehicle $vehicle,
        string $destinationType,
        int $destinationId,
        ?string $purpose = null,
        ?string $notes = null
    ): VehicleDeployment {
        return DB::transaction(function () use (
            $vehicle,
            $destinationType,
            $destinationId,
            $purpose,
            $notes
        ) {
            $this->validateDestination(
                $destinationType,
                $destinationId
            );

            $this->ensureNoActiveDeployment($vehicle);

            return VehicleDeployment::create([
                'vehicle_id' => $vehicle->id,
                'destination_type' => $destinationType,
                'destination_id' => $destinationId,
                'purpose' => $purpose,
                'status' => 'planned',
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Dispatch a planned deployment.
     */
    public function dispatch(
        VehicleDeployment $deployment
    ): VehicleDeployment {
        if ($deployment->status !== 'planned') {
            throw new RuntimeException(
                'Only planned deployments can be dispatched.'
            );
        }

        $deployment->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        return $deployment->fresh();
    }

    /**
     * Start a dispatched deployment.
     */
    public function start(
        VehicleDeployment $deployment
    ): VehicleDeployment {
        if ($deployment->status !== 'dispatched') {
            throw new RuntimeException(
                'Only dispatched deployments can be started.'
            );
        }

        $deployment->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return $deployment->fresh();
    }

    /**
     * Complete an active deployment.
     */
    public function complete(
        VehicleDeployment $deployment
    ): VehicleDeployment {
        if (! in_array(
            $deployment->status,
            ['dispatched', 'in_progress'],
            true
        )) {
            throw new RuntimeException(
                'Only dispatched or in-progress deployments can be completed.'
            );
        }

        $deployment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $deployment->fresh();
    }

    /**
     * Cancel a deployment.
     */
    public function cancel(
        VehicleDeployment $deployment
    ): VehicleDeployment {
        if (! in_array(
            $deployment->status,
            ['planned', 'dispatched'],
            true
        )) {
            throw new RuntimeException(
                'Only planned or dispatched deployments can be cancelled.'
            );
        }

        $deployment->update([
            'status' => 'cancelled',
        ]);

        return $deployment->fresh();
    }

    /**
     * Return the vehicle's current active deployment.
     */
    public function activeDeployment(
        Vehicle $vehicle
    ): ?VehicleDeployment {
        return $vehicle->deployments()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    /**
     * Determine the current routing destination.
     *
     * Active deployment destination takes priority.
     * Otherwise the vehicle routes to its permanent home shop.
     */
    public function routingDestination(
        Vehicle $vehicle
    ): ?array {
        $deployment = $this->activeDeployment($vehicle);

        if ($deployment !== null) {
            return $this->resolveDeploymentDestination(
                $deployment
            );
        }

        $shop = $vehicle->assignedShop;

        if ($shop === null) {
            return null;
        }

        return [
            'type' => 'shop',
            'id' => $shop->id,
            'name' => $shop->name,
            'latitude' => (float) $shop->latitude,
            'longitude' => (float) $shop->longitude,
        ];
    }

    /**
     * Calculate a road route from the vehicle's latest known GPS
     * position to its current routing destination.
     */
    public function calculateRoute(
        Vehicle $vehicle
    ): ?array {
        $position = $vehicle->positions()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('gps_time')
            ->first();

        if ($position === null) {
            return null;
        }

        $destination = $this->routingDestination($vehicle);

        if ($destination === null) {
            return null;
        }

        return $this->osrm->route(
            (float) $position->latitude,
            (float) $position->longitude,
            (float) $destination['latitude'],
            (float) $destination['longitude']
        );
    }

    /**
     * Resolve a deployment's destination coordinates.
     */
    private function resolveDeploymentDestination(
        VehicleDeployment $deployment
    ): ?array {
        if ($deployment->destination_type === 'shop') {
            $shop = Shop::find($deployment->destination_id);

            if ($shop === null) {
                throw new RuntimeException(
                    'Deployment destination shop does not exist.'
                );
            }

            return [
                'type' => 'shop',
                'id' => $shop->id,
                'name' => $shop->name,
                'latitude' => (float) $shop->latitude,
                'longitude' => (float) $shop->longitude,
            ];
        }

        if ($deployment->destination_type === 'location') {
            $location = Location::find(
                $deployment->destination_id
            );

            if ($location === null) {
                throw new RuntimeException(
                    'Deployment destination location does not exist.'
                );
            }

            return [
                'type' => 'location',
                'id' => $location->id,
                'name' => $location->name,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
            ];
        }

        throw new RuntimeException(
            'Unsupported deployment destination type: '.
            $deployment->destination_type
        );
    }

    /**
     * Validate that the destination exists and is active.
     */
    private function validateDestination(
        string $destinationType,
        int $destinationId
    ): void {
        if ($destinationType === 'shop') {
            $exists = Shop::whereKey($destinationId)
                ->where('active', true)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException(
                    'The selected shop does not exist or is inactive.'
                );
            }

            return;
        }

        if ($destinationType === 'location') {
            $exists = Location::whereKey($destinationId)
                ->where('active', true)
                ->exists();

            if (! $exists) {
                throw new InvalidArgumentException(
                    'The selected location does not exist or is inactive.'
                );
            }

            return;
        }

        throw new InvalidArgumentException(
            'Invalid destination type. Allowed values: shop, location.'
        );
    }

    /**
     * Prevent a vehicle from receiving multiple active deployments.
     */
    private function ensureNoActiveDeployment(
        Vehicle $vehicle
    ): void {
        $exists = $vehicle->deployments()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'This vehicle already has an active deployment.'
            );
        }
    }
}
