<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleEvent;
use Illuminate\Support\Facades\Log;

class RouteFenceAlertService
{
    public const ROUTE_FENCE_WIDTH_METERS = 20.0;

    public const ALERT_COOLDOWN_SECONDS = 300;

    public function checkAndTrigger(Vehicle $vehicle, float $latitude, float $longitude, ?float $speed = null): bool
    {
        $routeGeometry = $vehicle->route_geometry ?? null;

        if (! is_array($routeGeometry) || count($routeGeometry) < 2) {
            return false;
        }

        $distanceMeters = $this->distanceToPolylineMeters($latitude, $longitude, $routeGeometry);

        if ($distanceMeters <= self::ROUTE_FENCE_WIDTH_METERS) {
            return false;
        }

        if ($this->hasRecentBreach($vehicle)) {
            return false;
        }

        $vehicle->events()->create([
            'event_type' => 'route_fence_breach',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $speed,
            'event_time' => now()->unix(),
            'metadata' => [
                'distance_meters' => round($distanceMeters, 1),
                'allowed_distance_meters' => (int) self::ROUTE_FENCE_WIDTH_METERS,
                'route_destination_type' => $vehicle->route_destination_type,
                'route_destination_id' => $vehicle->route_destination_id,
            ],
        ]);

        Log::warning('Route fence breach detected.', [
            'vehicle_id' => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'distance_meters' => round($distanceMeters, 1),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => $speed,
            'allowed_distance_meters' => (int) self::ROUTE_FENCE_WIDTH_METERS,
        ]);

        return true;
    }

    protected function hasRecentBreach(Vehicle $vehicle): bool
    {
        return VehicleEvent::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('event_type', 'route_fence_breach')
            ->where('event_time', '>=', now()->subSeconds(self::ALERT_COOLDOWN_SECONDS)->unix())
            ->exists();
    }

    protected function distanceToPolylineMeters(float $latitude, float $longitude, array $routeGeometry): float
    {
        $shortestDistance = PHP_FLOAT_MAX;

        foreach ($routeGeometry as $index => $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            if ($index === 0) {
                continue;
            }

            [$previousLongitude, $previousLatitude] = $routeGeometry[$index - 1];
            [$currentLongitude, $currentLatitude] = $point;

            $distance = $this->distanceToSegmentMeters(
                $latitude,
                $longitude,
                (float) $previousLatitude,
                (float) $previousLongitude,
                (float) $currentLatitude,
                (float) $currentLongitude,
            );

            if ($distance < $shortestDistance) {
                $shortestDistance = $distance;
            }
        }

        return $shortestDistance === PHP_FLOAT_MAX ? 0.0 : $shortestDistance;
    }

    protected function distanceToSegmentMeters(
        float $pointLatitude,
        float $pointLongitude,
        float $segmentStartLatitude,
        float $segmentStartLongitude,
        float $segmentEndLatitude,
        float $segmentEndLongitude
    ): float {
        $segmentLengthSquared = (
            ($segmentEndLatitude - $segmentStartLatitude) ** 2
            + ($segmentEndLongitude - $segmentStartLongitude) ** 2
        );

        if ($segmentLengthSquared === 0.0) {
            return $this->distanceInMeters(
                $pointLatitude,
                $pointLongitude,
                $segmentStartLatitude,
                $segmentStartLongitude,
            );
        }

        $projection = (
            ($pointLatitude - $segmentStartLatitude) * ($segmentEndLatitude - $segmentStartLatitude)
            + ($pointLongitude - $segmentStartLongitude) * ($segmentEndLongitude - $segmentStartLongitude)
        ) / $segmentLengthSquared;

        $projection = max(0.0, min(1.0, $projection));

        $projectedLatitude = $segmentStartLatitude + ($projection * ($segmentEndLatitude - $segmentStartLatitude));
        $projectedLongitude = $segmentStartLongitude + ($projection * ($segmentEndLongitude - $segmentStartLongitude));

        return $this->distanceInMeters(
            $pointLatitude,
            $pointLongitude,
            $projectedLatitude,
            $projectedLongitude,
        );
    }

    protected function distanceInMeters(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;

        $lat1 = deg2rad($latitude1);
        $lat2 = deg2rad($latitude2);

        $deltaLat = deg2rad($latitude2 - $latitude1);
        $deltaLon = deg2rad($longitude2 - $longitude1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
