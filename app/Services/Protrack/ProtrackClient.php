<?php

namespace App\Services\Protrack;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProtrackClient
{
    private string $baseUrl;
    private string $account;
    private string $password;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('protrack.base_url');
        $this->account = config('protrack.account');
        $this->password = config('protrack.password');
        $this->timeout = (int) config('protrack.timeout', 30);
    }

    /**
     * Authenticate with Protrack365.
     *
     * Protrack signature:
     *
     * MD5(MD5(password) + timestamp)
     */
    public function getAccessToken(): string
    {
        $timestamp = time();

        $signature = md5(
            md5($this->password) . $timestamp
        );

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/authorization',
                [
                    'time' => $timestamp,
                    'account' => $this->account,
                    'signature' => $signature,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack HTTP request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack authentication failed: ' .
                $response->body()
            );
        }

        $token = $data['record']['access_token'] ?? null;

        if (!$token) {
            throw new RuntimeException(
                'Protrack did not return an access token.'
            );
        }

        return $token;
    }

    /**
     * Get all devices associated with the Protrack account.
     *
     * Protrack endpoint:
     *
     * GET /api/device/list
     */
    public function devices(): array
    {
        $token = $this->getAccessToken();

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/device/list',
                [
                    'access_token' => $token,
                    'account' => $this->account,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack device request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack device request failed: ' .
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get the latest tracking information for one or more devices.
     *
     * The Protrack API allows multiple IMEIs in a single request.
     *
     * Example:
     *
     * $client->track([
     *     '123456789012345',
     *     '987654321098765',
     * ]);
     */
    public function track(array $imeis = []): array
    {
        $token = $this->getAccessToken();

        $params = [
            'access_token' => $token,
        ];

        if (!empty($imeis)) {
            $params['imeis'] = implode(',', $imeis);
        }

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/track',
                $params
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack tracking request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack tracking request failed: ' .
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get historical playback data for a device.
     *
     * $imei     Device IMEI
     * $begintime Unix timestamp
     * $endtime   Unix timestamp
     */
    public function playback(
        string $imei,
        int $begintime,
        int $endtime
    ): array {
        $token = $this->getAccessToken();

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/playback',
                [
                    'access_token' => $token,
                    'imei' => $imei,
                    'begintime' => $begintime,
                    'endtime' => $endtime,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack playback request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack playback request failed: ' .
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get detailed information about a device.
     */
    public function deviceDetail(string $imei): array
    {
        $token = $this->getAccessToken();

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/device/detail',
                [
                    'access_token' => $token,
                    'imei' => $imei,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack device detail request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack device detail request failed: ' .
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get mileage information for devices.
     */
    public function mileage(
        array $imeis,
        int $begintime,
        int $endtime
    ): array {
        $token = $this->getAccessToken();

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/device/mileage',
                [
                    'access_token' => $token,
                    'imeis' => implode(',', $imeis),
                    'begintime' => $begintime,
                    'endtime' => $endtime,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack mileage request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack mileage request failed: ' .
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get alarm records.
     */
    public function alarms(
        int $begintime,
        int $endtime,
        array $imeis = []
    ): array {
        $token = $this->getAccessToken();

        $params = [
            'access_token' => $token,
            'begintime' => $begintime,
            'endtime' => $endtime,
        ];

        if (!empty($imeis)) {
            $params['imeis'] = implode(',', $imeis);
        }

        $response = Http::timeout($this->timeout)
            ->get(
                $this->baseUrl . '/api/alarm/list2',
                $params
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack alarm request failed: ' .
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack alarm request failed: ' .
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }
}