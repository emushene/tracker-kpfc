<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LocationIqService
{
    /**
     * Reverse geocode GPS coordinates using LocationIQ.
     */
    public function reverseGeocode(
        float $latitude,
        float $longitude
    ): array {
        $apiKey = config('locationiq.api_key');

        if (empty($apiKey)) {
            throw new RuntimeException(
                'LocationIQ API key is not configured.'
            );
        }

        $baseUrl = rtrim(
            config(
                'locationiq.base_url',
                'https://us1.locationiq.com'
            ),
            '/'
        );

        $response = Http::timeout(
            config('locationiq.timeout', 15)
        )->get(
            $baseUrl . '/v1/reverse',
            [
                'key' => $apiKey,
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1,
                'normalizeaddress' => 1,
            ]
        );

        if ($response->failed()) {
            throw new RuntimeException(
                'LocationIQ request failed. HTTP status: ' .
                $response->status() .
                '. Response: ' .
                $response->body()
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException(
                'LocationIQ returned an invalid response.'
            );
        }

        $address = $data['address'] ?? [];

        if (! is_array($address)) {
            $address = [];
        }

        return [
            'location_name' => $this->buildLocationName(
                $address
            ),

            'address' => $this->buildFullAddress(
                $address
            ),
        ];
    }

    /**
     * Build a concise location name from the available
     * LocationIQ address components.
     */
    private function buildLocationName(array $address): ?string
    {
        $fields = [
            'road',
            'suburb',
            'neighbourhood',
            'city',
            'town',
            'municipality',
            'county',
            'state',
        ];

        $parts = [];

        foreach ($fields as $field) {
            $value = $address[$field] ?? null;

            if (
                ! is_string($value) ||
                trim($value) === ''
            ) {
                continue;
            }

            $value = trim($value);

            if (
                ! in_array(
                    $value,
                    $parts,
                    true
                )
            ) {
                $parts[] = $value;
            }
        }

        if (empty($parts)) {
            return null;
        }

        return implode(', ', $parts);
    }

    /**
     * Build the fuller address including country.
     */
    private function buildFullAddress(array $address): ?string
    {
        $fields = [
            'road',
            'suburb',
            'neighbourhood',
            'city',
            'town',
            'municipality',
            'county',
            'state',
            'country',
        ];

        $parts = [];

        foreach ($fields as $field) {
            $value = $address[$field] ?? null;

            if (
                ! is_string($value) ||
                trim($value) === ''
            ) {
                continue;
            }

            $value = trim($value);

            if (
                ! in_array(
                    $value,
                    $parts,
                    true
                )
            ) {
                $parts[] = $value;
            }
        }

        if (empty($parts)) {
            return null;
        }

        return implode(', ', $parts);
    }
}