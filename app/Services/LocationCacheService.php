<?php

namespace App\Services;

use App\Models\LocationCache;

class LocationCacheService
{
    /**
     * Find a cached location close to the supplied GPS coordinates.
     */
    public function find(
        float $latitude,
        float $longitude,
        float $radiusMeters = 250
    ): ?LocationCache {
        $bucket = LocationCache::bucket(
            $latitude,
            $longitude
        );

        $cacheEntries = LocationCache::query()
            ->whereBetween(
                'latitude_bucket',
                [
                    (float) $bucket['latitude_bucket'] - 0.003,
                    (float) $bucket['latitude_bucket'] + 0.003,
                ]
            )
            ->whereBetween(
                'longitude_bucket',
                [
                    (float) $bucket['longitude_bucket'] - 0.003,
                    (float) $bucket['longitude_bucket'] + 0.003,
                ]
            )
            ->get();

        $nearest = null;
        $nearestDistance = null;

        foreach ($cacheEntries as $entry) {
            $distance = $this->distanceInMeters(
                $latitude,
                $longitude,
                (float) $entry->latitude,
                (float) $entry->longitude
            );

            if (
                $distance <= $radiusMeters &&
                (
                    $nearestDistance === null ||
                    $distance < $nearestDistance
                )
            ) {
                $nearest = $entry;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }

    /**
     * Save a newly resolved location.
     */
    public function store(
        float $latitude,
        float $longitude,
        ?string $locationName,
        ?string $address,
        ?string $provider = null
    ): LocationCache {
        $bucket = LocationCache::bucket(
            $latitude,
            $longitude
        );

        return LocationCache::create([
            'latitude' => $latitude,
            'longitude' => $longitude,

            'latitude_bucket' =>
                $bucket['latitude_bucket'],

            'longitude_bucket' =>
                $bucket['longitude_bucket'],

            'location_name' => $locationName,
            'address' => $address,
            'provider' => $provider,
        ]);
    }

    /**
     * Calculate distance between two GPS coordinates in metres.
     */
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
            sin($deltaLat / 2) ** 2 +
            cos($lat1) *
            cos($lat2) *
            sin($deltaLon / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;
    }
}