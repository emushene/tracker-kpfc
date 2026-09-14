<?php

use Illuminate\Support\Facades\Route;
use App\Services\Protrack\ProtrackClient;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/protrack/test', function (
    ProtrackClient $protrack
) {
    return [
        'status' => 'connected',
        'token_received' => !empty(
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