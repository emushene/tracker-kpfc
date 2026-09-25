<?php

use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Auth\KpfcSsoController;
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

// Protected Fleet API Routes (Requires Authentication, Fleet Access & Role-Based Write Enforcement)
Route::middleware(['web', 'auth', 'fleet.access', 'fleet.write'])->group(function (): void {
    // GET /api/shops - List active shops for map geofences and vehicle operations
    Route::get('/shops', [ShopController::class, 'index'])->name('api.shops.index');

    Route::prefix('vehicles')->group(function (): void {
        // GET /api/vehicles - List all vehicles with live locations, status, home shops, and active missions
        Route::get('/', [VehicleController::class, 'index'])->name('api.vehicles.index');

        // POST /api/vehicles - Create a new vehicle record (write role required)
        Route::post('/', [VehicleController::class, 'store'])->name('api.vehicles.store');

        // GET /api/vehicles/location - Query vehicle location by ?plate_number=, ?imei=, or ?id=
        Route::get('/location', [VehicleController::class, 'queryLocation'])->name('api.vehicles.query-location');

        // GET /api/vehicles/{vehicle} - Retrieve detailed information for a single vehicle
        Route::get('/{vehicle}', [VehicleController::class, 'show'])->name('api.vehicles.show');

        // GET /api/vehicles/{vehicle}/location - Retrieve vehicle live location and telemetry details
        Route::get('/{vehicle}/location', [VehicleController::class, 'location'])->name('api.vehicles.location');

        // POST /api/vehicles/{vehicle}/location - Update or ingest GPS position and location for vehicle (write role required)
        Route::post('/{vehicle}/location', [VehicleController::class, 'updateLocation'])->name('api.vehicles.update-location');

        // PATCH /api/vehicles/{vehicle}/assign-shop - Set or update the vehicle's permanent home shop (write role required)
        Route::patch('/{vehicle}/assign-shop', [VehicleController::class, 'assignShop'])->name('api.vehicles.assign-shop');

        // POST /api/vehicles/{vehicle}/deployments - Dispatch the vehicle to another shop or custom location (write role required)
        Route::post('/{vehicle}/deployments', [DeploymentController::class, 'store'])->name('api.vehicles.deployments.store');

        // PATCH /api/vehicles/{vehicle}/deployments/{deployment}/release - Complete the active deployment (write role required)
        Route::patch('/{vehicle}/deployments/{deployment}/release', [DeploymentController::class, 'release'])->name('api.vehicles.deployments.release');

        // PATCH /api/vehicles/{vehicle}/deployments/{deployment}/cancel - Cancel a planned or dispatched deployment (write role required)
        Route::patch('/{vehicle}/deployments/{deployment}/cancel', [DeploymentController::class, 'cancel'])->name('api.vehicles.deployments.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Fleet Maintenance API Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('maintenance')->group(function (): void {
        Route::get('/overview', [MaintenanceController::class, 'overview'])->name('api.maintenance.overview');
        Route::get('/tickets', [MaintenanceController::class, 'tickets'])->name('api.maintenance.tickets');
        Route::post('/tickets', [MaintenanceController::class, 'storeTicket'])->name('api.maintenance.tickets.store');
        Route::get('/job-cards', [MaintenanceController::class, 'jobCards'])->name('api.maintenance.job-cards');
        Route::post('/job-cards', [MaintenanceController::class, 'storeJobCard'])->name('api.maintenance.job-cards.store');
        Route::get('/alerts', [MaintenanceController::class, 'alerts'])->name('api.maintenance.alerts');
        Route::get('/vehicles/{vehicle}', [MaintenanceController::class, 'vehicleDetails'])->name('api.maintenance.vehicle-details');
    });

    /*
    |--------------------------------------------------------------------------
    | Parts & Tools Inventory API Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventory')->group(function (): void {
        Route::get('/overview', [InventoryController::class, 'overview'])->name('api.inventory.overview');
        Route::get('/parts', [InventoryController::class, 'parts'])->name('api.inventory.parts');
        Route::post('/parts', [InventoryController::class, 'storePart'])->name('api.inventory.parts.store');
        Route::post('/parts/{part}/adjust', [InventoryController::class, 'adjustStock'])->name('api.inventory.parts.adjust');
        Route::get('/tools', [InventoryController::class, 'tools'])->name('api.inventory.tools');
        Route::post('/tools/{tool}/assign', [InventoryController::class, 'assignTool'])->name('api.inventory.tools.assign');
        Route::post('/tools/{tool}/return', [InventoryController::class, 'returnTool'])->name('api.inventory.tools.return');
    });

    /*
    |--------------------------------------------------------------------------
    | Integration API Routes (External Systems / KPFC Admin & Business)
    |--------------------------------------------------------------------------
    */
    Route::prefix('integration/vehicles')->group(function (): void {
        Route::get('/', [VehicleController::class, 'index'])->name('api.integration.vehicles.index');
        Route::post('/', [VehicleController::class, 'store'])->name('api.integration.vehicles.store');
        Route::get('/location', [VehicleController::class, 'queryLocation'])->name('api.integration.vehicles.query-location');
        Route::get('/{vehicle}', [VehicleController::class, 'show'])->name('api.integration.vehicles.show');
        Route::get('/{vehicle}/location', [VehicleController::class, 'location'])->name('api.integration.vehicles.location');
        Route::post('/{vehicle}/location', [VehicleController::class, 'updateLocation'])->name('api.integration.vehicles.update-location');
    });
});

/*
|--------------------------------------------------------------------------
| KPFC Admin Single Sign-On (SSO) & Identity Routes
|--------------------------------------------------------------------------
*/

// Lifecycle Webhook Receiver from KPFC Admin
Route::post('/sso/webhook', [KpfcSsoController::class, 'webhook'])->name('api.sso.webhook');
Route::post('/auth/kpfc/webhook', [KpfcSsoController::class, 'webhook'])->name('api.auth.kpfc.webhook');

// OAuth PKCE browser flows (requires session state for state and verifier)
Route::middleware('web')->group(function (): void {
    Route::get('/auth/kpfc/redirect', [KpfcSsoController::class, 'redirect'])->name('api.auth.kpfc.redirect');
    Route::get('/auth/kpfc/callback', [KpfcSsoController::class, 'callback'])->name('api.auth.kpfc.callback');
    Route::post('/auth/kpfc/logout', [KpfcSsoController::class, 'logout'])->name('api.auth.kpfc.logout');
});
