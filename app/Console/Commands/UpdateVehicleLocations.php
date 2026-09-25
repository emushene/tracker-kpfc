<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\LocationCacheService;
use App\Services\LocationIqService;
use App\Services\ShopLocationService;
use Illuminate\Console\Command;
use Throwable;

class UpdateVehicleLocations extends Command
{
    protected $signature = 'vehicles:update-locations';

    protected $description = 'Determine and save the human-readable location of each vehicle';

    private const MOVEMENT_THRESHOLD_METERS = 200;

    private const LOCATION_CACHE_RADIUS_METERS = 250;

    public function handle(
        ShopLocationService $shopLocationService,
        LocationCacheService $locationCacheService,
        LocationIqService $locationIqService
    ): int {
        $vehicles = Vehicle::query()
            ->where('active', true)
            ->get();

        if ($vehicles->isEmpty()) {
            $this->info('No active vehicles found.');

            return self::SUCCESS;
        }

        foreach ($vehicles as $vehicle) {
            $position = $vehicle->positions()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest('gps_time')
                ->first();

            if (! $position) {
                $this->warn(
                    "No GPS position found for {$vehicle->imei}."
                );

                continue;
            }

            $latitude = (float) $position->latitude;
            $longitude = (float) $position->longitude;

            /*
             * Ignore invalid GPS coordinates.
             */
            if (
                $latitude == 0.0 &&
                $longitude == 0.0
            ) {
                $this->warn(
                    "{$vehicle->imei} -> Invalid GPS coordinates"
                );

                continue;
            }

            /*
             * 1. Check known shops and garage first.
             */
            $shop = $shopLocationService->findNearbyShop(
                $latitude,
                $longitude
            );

            if ($shop) {
                $vehicle->update([
                    'location_name' => $shop->name,
                    'location_latitude' => $latitude,
                    'location_longitude' => $longitude,
                    'location_updated_at' => now(),
                ]);

                $this->line(
                    "{$vehicle->imei} -> {$shop->name}"
                );

                continue;
            }

            /*
             * 2. If the vehicle has moved less than 200m
             * from its previous resolved location, keep
             * the existing location.
             */
            if (
                $vehicle->location_latitude !== null &&
                $vehicle->location_longitude !== null
            ) {
                $distance = $this->distanceInMeters(
                    (float) $vehicle->location_latitude,
                    (float) $vehicle->location_longitude,
                    $latitude,
                    $longitude
                );

                if (
                    $distance < self::MOVEMENT_THRESHOLD_METERS &&
                    ! empty($vehicle->location_name)
                ) {
                    $vehicle->update([
                        'location_latitude' => $latitude,
                        'location_longitude' => $longitude,
                        'location_updated_at' => now(),
                    ]);

                    $this->line(
                        "{$vehicle->imei} -> {$vehicle->location_name} ".
                        "(cached, {$this->formatDistance($distance)} away)"
                    );

                    continue;
                }
            }

            /*
             * 3. Look for a location already resolved by
             * another vehicle near the same coordinates.
             */
            $cachedLocation = $locationCacheService->find(
                $latitude,
                $longitude,
                self::LOCATION_CACHE_RADIUS_METERS
            );

            if ($cachedLocation) {
                $vehicle->update([
                    'location_name' => $cachedLocation->location_name,
                    'location_latitude' => $latitude,
                    'location_longitude' => $longitude,
                    'location_updated_at' => now(),
                ]);

                $this->line(
                    "{$vehicle->imei} -> {$cachedLocation->location_name} ".
                    '(location cache)'
                );

                continue;
            }

            /*
             * 4. No shop, movement cache or shared cache.
             * This is where we make the LocationIQ request.
             */
            try {
                $result = $locationIqService->reverseGeocode(
                    $latitude,
                    $longitude
                );

                $locationName = $result['location_name'] ?? null;
                $address = $result['address'] ?? null;

                /*
                 * Save the LocationIQ result in the shared
                 * location cache so nearby vehicles can
                 * reuse it.
                 */
                $locationCacheService->store(
                    $latitude,
                    $longitude,
                    $locationName,
                    $address,
                    'locationiq'
                );

                $vehicle->update([
                    'location_name' => $locationName,
                    'location_latitude' => $latitude,
                    'location_longitude' => $longitude,
                    'location_updated_at' => now(),
                ]);

                $this->line(
                    "{$vehicle->imei} -> ".
                    ($locationName ?? 'Location not found').
                    ' (LocationIQ)'
                );
            } catch (Throwable $exception) {
                /*
                 * Do not stop the entire synchronization if
                 * one LocationIQ request fails.
                 */
                $this->error(
                    "{$vehicle->imei} -> LocationIQ failed: ".
                    $exception->getMessage()
                );

                /*
                 * Keep the GPS coordinates even when the
                 * human-readable lookup fails.
                 */
                $vehicle->update([
                    'location_latitude' => $latitude,
                    'location_longitude' => $longitude,
                    'location_updated_at' => now(),
                ]);
            }
        }

        return self::SUCCESS;
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

    /**
     * Format a distance for console output.
     */
    private function formatDistance(float $distance): string
    {
        if ($distance < 1000) {
            return round($distance).'m';
        }

        return round($distance / 1000, 2).'km';
    }
}
