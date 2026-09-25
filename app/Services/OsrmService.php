<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OsrmService
{
    private string $baseUrl = 'https://router.project-osrm.org';

    public function route(
        float $startLatitude,
        float $startLongitude,
        float $endLatitude,
        float $endLongitude
    ): array {
        $coordinates =
            $startLongitude.','.$startLatitude.
            ';'.
            $endLongitude.','.$endLatitude;

        $response = Http::timeout(15)
            ->get(
                $this->baseUrl.
                '/route/v1/driving/'.
                $coordinates,
                [
                    'overview' => 'false',
                    'steps' => 'false',
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'OSRM request failed. HTTP status: '.
                $response->status().
                '. Response: '.
                $response->body()
            );
        }

        $data = $response->json();

        if (
            ! is_array($data) ||
            ($data['code'] ?? null) !== 'Ok' ||
            empty($data['routes'])
        ) {
            throw new RuntimeException(
                'OSRM returned an invalid route response.'
            );
        }

        $route = $data['routes'][0];

        return [
            'distance_meters' => (int) round(
                (float) ($route['distance'] ?? 0)
            ),
            'duration_seconds' => (int) round(
                (float) ($route['duration'] ?? 0)
            ),
        ];
    }
}
