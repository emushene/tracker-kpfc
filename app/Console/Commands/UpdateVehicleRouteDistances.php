<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\OsrmService;
use Illuminate\Console\Command;
use Throwable;

class UpdateVehicleRouteDistances extends Command
{
    protected $signature = 'vehicles:update-route-distances';

    protected $description = 'Calculate road distance and driving duration from each vehicle to its assigned shop';

    public function handle(
        OsrmService $osrmService
    ): int {
        $vehicles = Vehicle::query()
            ->where('active', true)
            ->whereNotNull('assigned_shop_id')
            ->with('assignedShop')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->info(
                'No active vehicles with assigned shops found.'
            );

            return self::SUCCESS;
        }

        $this->info(
            "Processing {$vehicles->count()} assigned vehicles..."
        );

        foreach ($vehicles as $vehicle) {
            $shop = $vehicle->assignedShop;

            if (! $shop) {
                $this->warn(
                    "{$vehicle->imei} -> Assigned shop not found."
                );

                continue;
            }

            $position = $vehicle->positions()
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->latest('gps_time')
                ->first();

            if (! $position) {
                $this->warn(
                    "{$vehicle->imei} -> No GPS position found."
                );

                continue;
            }

            $vehicleLatitude = (float) $position->latitude;
            $vehicleLongitude = (float) $position->longitude;

            if (
                $vehicleLatitude == 0.0 &&
                $vehicleLongitude == 0.0
            ) {
                $this->warn(
                    "{$vehicle->imei} -> Invalid GPS coordinates."
                );

                continue;
            }

            $shopLatitude = (float) $shop->latitude;
            $shopLongitude = (float) $shop->longitude;

            try {
                $route = $osrmService->route(
                    $vehicleLatitude,
                    $vehicleLongitude,
                    $shopLatitude,
                    $shopLongitude
                );

                $vehicle->update([
                    'road_distance_meters' =>
                        $route['distance_meters'],

                    'road_duration_seconds' =>
                        $route['duration_seconds'],

                    'route_calculated_at' => now(),
                ]);

                $distanceKm =
                    $route['distance_meters'] / 1000;

                $durationMinutes =
                    $route['duration_seconds'] / 60;

                $this->line(
                    "{$vehicle->imei} -> ".
                    "{$shop->name} | ".
                    round($distanceKm, 2)." km | ".
                    round($durationMinutes)." min"
                );
            } catch (Throwable $exception) {
                $this->error(
                    "{$vehicle->imei} -> OSRM failed: ".
                    $exception->getMessage()
                );
            }
        }

        return self::SUCCESS;
    }
}