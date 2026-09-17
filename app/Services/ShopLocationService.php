<?php

namespace App\Services;

use App\Models\Shop;

class ShopLocationService
{
    /**
     * Find the nearest active shop within its configured radius.
     */
    public function findNearbyShop(
        float $latitude,
        float $longitude
    ): ?Shop {
        $shops = Shop::query()
            ->where('active', true)
            ->get();

        $nearestShop = null;
        $nearestDistance = null;

        foreach ($shops as $shop) {
            $distance = $this->distanceInMeters(
                $latitude,
                $longitude,
                (float) $shop->latitude,
                (float) $shop->longitude
            );

            if (
                $distance <= $shop->radius_meters &&
                (
                    $nearestDistance === null ||
                    $distance < $nearestDistance
                )
            ) {
                $nearestShop = $shop;
                $nearestDistance = $distance;
            }
        }

        return $nearestShop;
    }

    /**
     * Calculate distance between two GPS coordinates in metres.
     */
    public function distanceInMeters(
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
