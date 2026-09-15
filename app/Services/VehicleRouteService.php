<?php

namespace App\Services;

use App\Models\Vehicle;

class VehicleRouteService
{
    private const RECALCULATION_DISTANCE_METERS = 800;

    public function __construct(
        private OsrmService $osrm,
        private VehicleDeploymentService $deployments
    ) {
    }

    public function updateRoute(Vehicle $vehicle): bool
    {
        $position = $vehicle->positions()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('gps_time')
            ->first();

        if ($position === null) {
            return false;
        }

        $latitude = (float) $position->latitude;
        $longitude = (float) $position->longitude;

        // Ignore invalid GPS coordinates.
        if (
            $latitude === 0.0 &&
            $longitude === 0.0
        ) {
            return false;
        }

        /*
         * Determine where the vehicle should currently be routed to.
         *
         * If the vehicle has an active deployment:
         *     current GPS -> deployment destination
         *
         * Otherwise:
         *     current GPS -> permanent/home shop
         */
        $destination = $this->deployments
            ->routingDestination($vehicle);

        if ($destination === null) {
            return false;
        }

        /*
         * A destination change must force an immediate OSRM
         * recalculation, regardless of how far the vehicle moved.
         */
        $destinationChanged =
            $vehicle->route_destination_type !== $destination['type']
            ||
            (int) $vehicle->route_destination_id !==
                (int) $destination['id'];

        if ($destinationChanged) {
            return $this->calculateAndStoreRoute(
                $vehicle,
                $latitude,
                $longitude,
                $destination
            );
        }

        /*
         * No previous route reference means this is the first
         * route calculation.
         */
        if (
            $vehicle->route_latitude === null ||
            $vehicle->route_longitude === null
        ) {
            return $this->calculateAndStoreRoute(
                $vehicle,
                $latitude,
                $longitude,
                $destination
            );
        }

        /*
         * Calculate how far the vehicle has moved since the
         * position where the previous OSRM route was calculated.
         */
        $distanceMoved = $this->distanceInMeters(
            (float) $vehicle->route_latitude,
            (float) $vehicle->route_longitude,
            $latitude,
            $longitude
        );

        /*
         * Do not call OSRM unless the vehicle has moved
         * more than 800 metres.
         */
        if (
            $distanceMoved <=
            self::RECALCULATION_DISTANCE_METERS
        ) {
            return false;
        }

        return $this->calculateAndStoreRoute(
            $vehicle,
            $latitude,
            $longitude,
            $destination
        );
    }

    private function calculateAndStoreRoute(
        Vehicle $vehicle,
        float $latitude,
        float $longitude,
        array $destination
    ): bool {
        $route = $this->osrm->route(
            $latitude,
            $longitude,
            (float) $destination['latitude'],
            (float) $destination['longitude']
        );

        /*
         * Only update the route reference after OSRM succeeds.
         */
        $vehicle->update([
            'road_distance_meters' => $route['distance_meters'],
            'road_duration_seconds' => $route['duration_seconds'],
            'route_calculated_at' => now(),

            // Position used for this OSRM calculation.
            'route_latitude' => $latitude,
            'route_longitude' => $longitude,

            // Destination used for this OSRM calculation.
            'route_destination_type' => $destination['type'],
            'route_destination_id' => $destination['id'],
        ]);

        return true;
    }

    private function distanceInMeters(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $lat1 = deg2rad($latitude1);
        $lat2 = deg2rad($latitude2);

        $deltaLat = deg2rad(
            $latitude2 - $latitude1
        );

        $deltaLon = deg2rad(
            $longitude2 - $longitude1
        );

        $a =
            sin($deltaLat / 2) ** 2
            +
            cos($lat1)
            * cos($lat2)
            * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}