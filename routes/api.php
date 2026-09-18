<?php

use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Fleet Management API Routes
|--------------------------------------------------------------------------
|
| These routes provide endpoints for fleet managers to monitor vehicles,
| assign permanent home shops, and dispatch vehicles on deployments.
|Deployment operations include dispatching vehicles to other shops or custom locations,
| as well as releasing or canceling active deployments. All routes are
| protected by authentication and authorization middleware to ensure that only
| authorized fleet managers can access these endpoints.
*/

// GET /api/shops - List active shops for map geofences and vehicle operations
Route::get('/shops', [ShopController::class, 'index'])->name('api.shops.index');

Route::prefix('vehicles')->group(function (): void {
    // GET /api/vehicles - List all vehicles with live locations, status, home shops, and active missions
    Route::get('/', [VehicleController::class, 'index'])->name('api.vehicles.index');

    // GET /api/vehicles/{vehicle} - Retrieve detailed information for a single vehicle
    Route::get('/{vehicle}', [VehicleController::class, 'show'])->name('api.vehicles.show');

    // PATCH /api/vehicles/{vehicle}/assign-shop - Set or update the vehicle's permanent home shop
    Route::patch('/{vehicle}/assign-shop', [VehicleController::class, 'assignShop'])->name('api.vehicles.assign-shop');

    // POST /api/vehicles/{vehicle}/deployments - Dispatch the vehicle to another shop or custom location
    Route::post('/{vehicle}/deployments', [DeploymentController::class, 'store'])->name('api.vehicles.deployments.store');

    // PATCH /api/vehicles/{vehicle}/deployments/{deployment}/release - Complete the active deployment
    Route::patch('/{vehicle}/deployments/{deployment}/release', [DeploymentController::class, 'release'])->name('api.vehicles.deployments.release');

    // PATCH /api/vehicles/{vehicle}/deployments/{deployment}/cancel - Cancel a planned or dispatched deployment
    Route::patch('/{vehicle}/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('api.vehicles.deployments.cancel');
});
