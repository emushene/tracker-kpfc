<?php

namespace App\Services\Protrack;

use App\Models\Vehicle;
use App\Services\VehicleRouteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProtrackClient
{
    private string $baseUrl;

    private string $account;

    private string $password;

    private int $timeout;

    private string $vehicleAccount;

    public function __construct(
        private VehicleRouteService $routeService
    ) {
        $this->baseUrl = config('protrack.base_url');
        $this->account = config('protrack.account');
        $this->password = config('protrack.password');
        $this->timeout = (int) config('protrack.timeout', 30);

        $this->vehicleAccount = config(
            'protrack.vehicle_account',
            'kpfctrack1'
        );
    }

    /**
     * Create the HTTP client used for Protrack365.
     *
     * The production Debian server has a proxy environment
     * that interferes with PHP cURL connections to Protrack365.
     * Explicitly disabling the proxy keeps Protrack traffic
     * direct while leaving the rest of the application unchanged.
     */
    private function http()
    {
        return Http::timeout($this->timeout)
            ->withOptions([
                'proxy' => '',
            ]);
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
            md5($this->password).$timestamp
        );

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/authorization',
                [
                    'time' => $timestamp,
                    'account' => $this->account,
                    'signature' => $signature,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack HTTP request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack authentication failed: '.
                $response->body()
            );
        }

        $token = $data['record']['access_token'] ?? null;

        if (! $token) {
            throw new RuntimeException(
                'Protrack did not return an access token.'
            );
        }

        return $token;
    }

    /**
     * Get all devices associated with the configured
     * Protrack vehicle account.
     *
     * Authentication is performed using the admin account,
     * while the device list is requested for the configured
     * vehicle account.
     *
     * Default vehicle account:
     *
     * kpfctrack1
     */
    public function devices(): array
    {
        $token = $this->getAccessToken();

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/device/list',
                [
                    'access_token' => $token,
                    'account' => $this->vehicleAccount,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack device request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack device request failed: '.
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

        if (! empty($imeis)) {
            $params['imeis'] = implode(',', $imeis);
        }

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/track',
                $params
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack tracking request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack tracking request failed: '.
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get historical playback data for a device.
     *
     * $imei       Device IMEI
     * $begintime   Unix timestamp
     * $endtime     Unix timestamp
     */
    public function playback(
        string $imei,
        int $begintime,
        int $endtime
    ): array {
        $token = $this->getAccessToken();

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/playback',
                [
                    'access_token' => $token,
                    'imei' => $imei,
                    'begintime' => $begintime,
                    'endtime' => $endtime,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack playback request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack playback request failed: '.
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

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/device/detail',
                [
                    'access_token' => $token,
                    'imei' => $imei,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack device detail request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack device detail request failed: '.
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get mileage information for devices.
     *
     * $imeis       Array of device IMEIs
     * $begintime   Unix timestamp
     * $endtime     Unix timestamp
     */
    public function mileage(
        array $imeis,
        int $begintime,
        int $endtime
    ): array {
        $token = $this->getAccessToken();

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/device/mileage',
                [
                    'access_token' => $token,
                    'imeis' => implode(',', $imeis),
                    'begintime' => $begintime,
                    'endtime' => $endtime,
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack mileage request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack mileage request failed: '.
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    /**
     * Get alarm records.
     *
     * $begintime   Unix timestamp
     * $endtime     Unix timestamp
     * $imeis       Optional array of device IMEIs
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

        if (! empty($imeis)) {
            $params['imeis'] = implode(',', $imeis);
        }

        $response = $this->http()
            ->get(
                $this->baseUrl.'/api/alarm/list2',
                $params
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Protrack alarm request failed: '.
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['code'] ?? null) !== 0) {
            throw new RuntimeException(
                'Protrack alarm request failed: '.
                $response->body()
            );
        }

        return $data['record'] ?? [];
    }

    // Sync Vehicles to the Database
    public function syncVehicles(): int
    {
        $devices = $this->devices();

        $count = 0;

        foreach ($devices as $device) {
            if (empty($device['imei'])) {
                continue;
            }

            Vehicle::updateOrCreate(
                [
                    'imei' => $device['imei'],
                ],
                [
                    'device_name' => $device['devicename'] ?? null,
                    'plate_number' => $device['platenumber'] ?? null,
                    'device_type' => $device['devicetype'] ?? null,
                    'simcard' => $device['simcard'] ?? null,
                    'iccid' => $device['iccid'] ?? null,

                    'activated_at' => ! empty($device['activatedtime'])
                        ? Carbon::createFromTimestamp(
                            $device['activatedtime']
                        )
                        : null,

                    'online_at' => ! empty($device['onlinetime'])
                        ? Carbon::createFromTimestamp(
                            $device['onlinetime']
                        )
                        : null,

                    'platform_due_at' => ! empty($device['platformduetime'])
                        ? Carbon::createFromTimestamp(
                            $device['platformduetime']
                        )
                        : null,

                    'active' => true,
                ]
            );

            $count++;
        }

        return $count;
    }

    // fleet tracking synchronization
    public function syncPositions(): int
    {
        $vehicles = Vehicle::query()
            ->where('active', true)
            ->get();

        if ($vehicles->isEmpty()) {
            return 0;
        }

        $imeis = $vehicles
            ->pluck('imei')
            ->filter()
            ->values()
            ->all();

        $records = $this->track($imeis);

        $count = 0;

        foreach ($records as $record) {
            $imei = $record['imei'] ?? null;

            if (! $imei) {
                continue;
            }

            $vehicle = $vehicles->firstWhere('imei', $imei);

            if (! $vehicle) {
                continue;
            }

            $gpsTime = $this->nullableTimestamp(
                $record['gpstime'] ?? null
            );

            /*
             * Don't insert the same GPS position twice.
             */
            if (
                $gpsTime !== null &&
                $vehicle->last_position_at !== null &&
                $gpsTime <= $vehicle->last_position_at->timestamp
            ) {
                continue;
            }

            /*
             * Store the new GPS position.
             */
            $vehicle->positions()->create([
                'latitude' => $this->nullableNumber(
                    $record['latitude'] ?? null
                ),

                'longitude' => $this->nullableNumber(
                    $record['longitude'] ?? null
                ),

                'speed' => $this->nullableNumber(
                    $record['speed'] ?? null
                ),

                'course' => $this->nullableNumber(
                    $record['course'] ?? null
                ),

                'battery' => $this->nullableNumber(
                    $record['battery'] ?? null
                ),

                'mileage' => $this->nullableInteger(
                    $record['mileage'] ?? null
                ),

                'today_mileage' => $this->nullableInteger(
                    $record['todaymileage'] ?? null
                ),

                'odometer' => $this->nullableInteger(
                    $record['odometer'] ?? null
                ),

                'acc_status' => $this->nullableInteger(
                    $record['accstatus'] ?? null
                ),

                'charge_status' => $this->nullableInteger(
                    $record['chargestatus'] ?? null
                ),

                'oil_power_status' => $this->nullableInteger(
                    $record['oilpowerstatus'] ?? null
                ),

                'door_status' => $this->nullableInteger(
                    $record['doorstatus'] ?? null
                ),

                'defence_status' => $this->nullableInteger(
                    $record['defencestatus'] ?? null
                ),

                'data_status' => $this->nullableInteger(
                    $record['datastatus'] ?? null
                ),

                'fuel' => $record['fuel'] ?: null,

                'external_power' => $record['externalpower'] ?: null,

                'heart_time' => $this->nullableTimestamp(
                    $record['hearttime'] ?? null
                ),

                'gps_time' => $gpsTime,

                'server_time' => $this->nullableTimestamp(
                    $record['servertime'] ?? null
                ),

                'system_time' => $this->nullableTimestamp(
                    $record['systemtime'] ?? null
                ),

                'temperature' => ! empty($record['temperature'])
                    ? $record['temperature']
                    : null,
            ]);

            /*
             * Update the vehicle's latest GPS timestamp.
             */
            if ($gpsTime !== null) {
                $vehicle->update([
                    'last_position_at' => Carbon::createFromTimestamp(
                        $gpsTime
                    ),
                ]);
            }

            /*
             * Route calculation must never prevent GPS synchronization.
             *
             * VehicleRouteService itself decides whether OSRM is needed:
             *
             * - destination changed -> recalculate
             * - first route -> calculate
             * - vehicle moved more than 800m -> recalculate
             * - otherwise -> skip
             *
             * If OSRM or route calculation fails, the GPS position
             * has already been safely stored.
             */
            try {
                $this->routeService->updateRoute(
                    $vehicle->fresh()
                );
            } catch (\Throwable $e) {
                \Log::error(
                    'Vehicle route calculation failed.',
                    [
                        'vehicle_id' => $vehicle->id,
                        'imei' => $vehicle->imei,
                        'error' => $e->getMessage(),
                    ]
                );
            }

            $count++;
        }

        return $count;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 0 ? $value : null;
    }

    private function nullableTimestamp(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
