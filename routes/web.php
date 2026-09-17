<?php

use App\Models\Vehicle;
use App\Services\Protrack\ProtrackClient;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// THIS IS A TEST ROUTE FOR THE DASHBOARD, REMOVE IT WHEN DONE
Route::get('/test-dashboard', function () {
    return response()->file(public_path('test-dashboard.html'));
});

Route::get('/protrack/test', function (
    ProtrackClient $protrack
) {
    return [
        'status' => 'connected',
        'token_received' => ! empty(
            $protrack->getAccessToken()
        ),
    ];
});

Route::get('/protrack/devices', function (
    ProtrackClient $protrack
) {
    return response()->json(
        $protrack->devices()
    );
});

Route::get('/protrack/device-count', function (
    ProtrackClient $protrack
) {
    $devices = $protrack->devices();

    return response()->json([
        'count' => count($devices),
        'devices' => $devices,
    ]);
});

Route::get('/protrack/accounts', function (
    ProtrackClient $protrack
) {
    $accounts = config('protrack.accounts', []);

    $result = [];

    foreach ($accounts as $account) {
        $devices = $protrack->devicesForAccount($account);

        $result[] = [
            'account' => $account,
            'device_count' => count($devices),
            'devices' => $devices,
        ];
    }

    return response()->json($result);
});

Route::get('/protrack/track/{imei}', function (
    string $imei
) {
    $vehicle = Vehicle::query()
        ->where('imei', $imei)
        ->where('active', true)
        ->first();

    if (! $vehicle) {
        return response()->json([
            'error' => 'Vehicle not found',
            'imei' => $imei,
        ], 404);
    }

    $position = $vehicle->positions()
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->latest('gps_time')
        ->first();

    if (! $position) {
        return response()->json([
            'error' => 'No GPS position found',
            'imei' => $imei,
        ], 404);
    }

    return response()->json([
        'imei' => $vehicle->imei,

        'device_name' => $vehicle->device_name,

        'plate_number' => $vehicle->plate_number,

        'latitude' => $position->latitude,

        'longitude' => $position->longitude,

        'speed' => $position->speed,

        'course' => $position->course,

        'gps_time' => $position->gps_time,

        'server_time' => $position->server_time,

        'location' => $vehicle->location_name,

        'location_updated_at' => $vehicle->location_updated_at,
    ]);
});
