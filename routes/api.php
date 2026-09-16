<?php

use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fleet Management API Routes
|--------------------------------------------------------------------------
|
| These routes provide endpoints for fleet managers to monitor vehicles,
| assign permanent home shops, and dispatch vehicles on deployments.
|
*/

Route::prefix('vehicles')->group(function (): void {
    // GET /api/vehicles - List all vehicles with live locations, status, home shops, and active missions
    Route::get('/', [VehicleController::class, 'index'])->name('api.vehicles.index');

    // GET /api/vehicles/{vehicle} - Retrieve detailed information for a single vehicle
    Route::get('/{vehicle}', [VehicleController::class, 'show'])->name('api.vehicles.show');

    // PATCH /api/vehicles/{vehicle}/assign-shop - Set or update the vehicle's permanent home shop
    Route::patch('/{vehicle}/assign-shop', [VehicleController::class, 'assignShop'])->name('api.vehicles.assign-shop');

    // POST /api/vehicles/{vehicle}/deployments - Dispatch the vehicle to another shop or custom location
    Route::post('/{vehicle}/deployments', [DeploymentController::class, 'store'])->name('api.vehicles.deployments.store');
});
