<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as administrator for integration endpoints
        $this->actingAs(User::factory()->admin()->create());

        // Fake OSRM HTTP requests for hermetic testing
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 12000,
                        'duration' => 900,
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * Test creating a new vehicle via POST /api/vehicles.
     */
    public function test_can_create_vehicle_via_api(): void
    {
        $shop = Shop::create([
            'name' => 'Westlands Hub',
            'code' => 'KPFC-002',
            'address' => 'Westlands, Nairobi',
            'latitude' => -1.2683,
            'longitude' => 36.8111,
            'radius_meters' => 300,
            'active' => true,
        ]);

        $payload = [
            'imei' => '867530901234567',
            'plate_number' => 'KDD 456B',
            'device_name' => 'Delivery Van 02',
            'device_type' => 'GPS-Tracker-v2',
            'simcard' => '0712345678',
            'iccid' => '8925400000000000001',
            'assigned_shop_id' => $shop->id,
            'active' => true,
        ];

        $response = $this->postJson('/api/vehicles', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Vehicle successfully created.')
            ->assertJsonPath('vehicle.imei', '867530901234567')
            ->assertJsonPath('vehicle.plate_number', 'KDD 456B')
            ->assertJsonPath('vehicle.assigned_shop_id', $shop->id)
            ->assertJsonPath('vehicle.assigned_shop.name', 'Westlands Hub');

        $this->assertDatabaseHas('vehicles', [
            'imei' => '867530901234567',
            'plate_number' => 'KDD 456B',
            'assigned_shop_id' => $shop->id,
        ]);
    }

    /**
     * Test vehicle creation validation rules.
     */
    public function test_create_vehicle_validation_rules(): void
    {
        // 1. Missing IMEI fails
        $response = $this->postJson('/api/vehicles', [
            'plate_number' => 'KDD 001A',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['imei']);

        // 2. Duplicate IMEI fails
        Vehicle::create([
            'imei' => '111112222233333',
            'plate_number' => 'KDD 002A',
        ]);

        $duplicateResponse = $this->postJson('/api/vehicles', [
            'imei' => '111112222233333',
            'plate_number' => 'KDD 003A',
        ]);
        $duplicateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['imei']);

        // 3. Inactive assigned shop fails
        $inactiveShop = Shop::create([
            'name' => 'Old Branch',
            'latitude' => -1.2800,
            'longitude' => 36.8100,
            'active' => false,
        ]);

        $shopResponse = $this->postJson('/api/vehicles', [
            'imei' => '999998888877777',
            'assigned_shop_id' => $inactiveShop->id,
        ]);
        $shopResponse->assertStatus(422)
            ->assertJsonValidationErrors(['assigned_shop_id']);
    }

    /**
     * Test retrieving vehicle location by ID via GET /api/vehicles/{id}/location.
     */
    public function test_can_get_vehicle_location_by_id(): void
    {
        $shop = Shop::create([
            'name' => 'Nairobi HQ',
            'code' => 'KPFC-001',
            'latitude' => -1.286389,
            'longitude' => 36.817223,
            'radius_meters' => 500,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '352093081234567',
            'plate_number' => 'KDD 789C',
            'device_name' => 'Lorry 03',
            'assigned_shop_id' => $shop->id,
            'location_name' => 'Mombasa Road',
            'location_latitude' => -1.3000,
            'location_longitude' => 36.8300,
            'location_updated_at' => now(),
            'road_distance_meters' => 12000,
            'road_duration_seconds' => 900,
            'active' => true,
        ]);

        VehiclePosition::create([
            'vehicle_id' => $vehicle->id,
            'latitude' => -1.3000,
            'longitude' => 36.8300,
            'speed' => 52.4,
            'course' => 90.0,
            'acc_status' => 1,
            'battery' => 13.2,
            'odometer' => 85200,
            'mileage' => 85200,
            'gps_time' => now()->timestamp,
        ]);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}/location");

        $response->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.plate_number', 'KDD 789C')
            ->assertJsonPath('data.imei', '352093081234567')
            ->assertJsonPath('data.status', 'moving')
            ->assertJsonPath('data.location.name', 'Mombasa Road')
            ->assertJsonPath('data.location.latitude', -1.3000)
            ->assertJsonPath('data.location.longitude', 36.8300)
            ->assertJsonPath('data.telemetry.speed', 52.4)
            ->assertJsonPath('data.telemetry.ignition_on', true)
            ->assertJsonPath('data.telemetry.odometer', 85200)
            ->assertJsonPath('data.assigned_shop.name', 'Nairobi HQ')
            ->assertJsonPath('data.routing.distance_km', 12)
            ->assertJsonPath('data.routing.duration_minutes', 15);
    }

    /**
     * Test retrieving vehicle location by plate number or IMEI route binding.
     */
    public function test_can_get_vehicle_location_by_plate_number_and_imei(): void
    {
        $vehicle = Vehicle::create([
            'imei' => '998877665544332',
            'plate_number' => 'KCA 321Z',
            'device_name' => 'Transit Van',
            'location_name' => 'Industrial Area',
            'location_latitude' => -1.3120,
            'location_longitude' => 36.8450,
            'active' => true,
        ]);

        // 1. By plate number with space
        $resByPlate = $this->getJson('/api/vehicles/'.rawurlencode('KCA 321Z').'/location');
        $resByPlate->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.plate_number', 'KCA 321Z');

        // 2. By plate number without space
        $resByPlateNoSpace = $this->getJson('/api/vehicles/KCA321Z/location');
        $resByPlateNoSpace->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id);

        // 3. By IMEI
        $resByImei = $this->getJson("/api/vehicles/{$vehicle->imei}/location");
        $resByImei->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.imei', '998877665544332');
    }

    /**
     * Test querying vehicle location via GET /api/vehicles/location query parameters.
     */
    public function test_can_query_vehicle_location_via_query_params(): void
    {
        $vehicle = Vehicle::create([
            'imei' => '554433221100998',
            'plate_number' => 'KBZ 999X',
            'location_name' => 'Airport Junction',
            'location_latitude' => -1.3320,
            'location_longitude' => 36.8920,
            'active' => true,
        ]);

        // Query by ?plate_number=
        $resPlate = $this->getJson('/api/vehicles/location?plate_number=KBZ 999X');
        $resPlate->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id);

        // Query by ?imei=
        $resImei = $this->getJson("/api/vehicles/location?imei={$vehicle->imei}");
        $resImei->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id);

        // Query by ?id=
        $resId = $this->getJson("/api/vehicles/location?id={$vehicle->id}");
        $resId->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicle->id);

        // Query with missing parameters -> 400
        $resEmpty = $this->getJson('/api/vehicles/location');
        $resEmpty->assertStatus(400);

        // Query for non-existent vehicle -> 404
        $resMissing = $this->getJson('/api/vehicles/location?plate_number=NONEXISTENT');
        $resMissing->assertStatus(404);
    }

    /**
     * Test updating/providing vehicle location via POST /api/vehicles/{vehicle}/location.
     */
    public function test_can_update_vehicle_location_via_post(): void
    {
        $vehicle = Vehicle::create([
            'imei' => '776655443322110',
            'plate_number' => 'KDG 777M',
            'active' => true,
        ]);

        $updatePayload = [
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'location_name' => 'Custom Landmark HQ',
            'speed' => 45.2,
            'course' => 180.0,
            'acc_status' => 1,
            'battery' => 12.8,
            'odometer' => 14000,
        ];

        $response = $this->postJson("/api/vehicles/{$vehicle->id}/location", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Vehicle location updated successfully.')
            ->assertJsonPath('vehicle.vehicle_id', $vehicle->id)
            ->assertJsonPath('vehicle.location.name', 'Custom Landmark HQ')
            ->assertJsonPath('vehicle.location.latitude', -1.2921)
            ->assertJsonPath('vehicle.location.longitude', 36.8219)
            ->assertJsonPath('vehicle.telemetry.speed', 45.2)
            ->assertJsonPath('vehicle.telemetry.odometer', 14000);

        $this->assertDatabaseHas('vehicle_positions', [
            'vehicle_id' => $vehicle->id,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'speed' => 45.2,
            'odometer' => 14000,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'location_name' => 'Custom Landmark HQ',
            'location_latitude' => -1.2921,
            'location_longitude' => 36.8219,
        ]);
    }

    /**
     * Test automatic resolution of shop geofence name when location_name is omitted.
     */
    public function test_location_update_auto_resolves_shop_geofence(): void
    {
        $shop = Shop::create([
            'name' => 'Karen Depot',
            'code' => 'KPFC-004',
            'latitude' => -1.3195,
            'longitude' => 36.7065,
            'radius_meters' => 500,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '665544332211009',
            'plate_number' => 'KDG 888N',
            'active' => true,
        ]);

        // Submit GPS coordinates right within Karen Depot's radius without providing location_name
        $response = $this->postJson("/api/vehicles/{$vehicle->id}/location", [
            'latitude' => -1.3196,
            'longitude' => 36.7066,
            'speed' => 0.0,
            'acc_status' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('vehicle.location.name', 'Karen Depot')
            ->assertJsonPath('vehicle.status', 'parked');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'location_name' => 'Karen Depot',
        ]);
    }

    /**
     * Test that /api/integration/vehicles routes function identically.
     */
    public function test_integration_routes_work_identically(): void
    {
        // 1. Create vehicle via /api/integration/vehicles
        $createRes = $this->postJson('/api/integration/vehicles', [
            'imei' => '332211009988776',
            'plate_number' => 'KDH 100P',
            'device_name' => 'Integration Fleet Van',
        ]);
        $createRes->assertStatus(201);
        $vehicleId = $createRes->json('vehicle.id');

        // 2. Update location via /api/integration/vehicles/{vehicle}/location
        $updateRes = $this->postJson("/api/integration/vehicles/{$vehicleId}/location", [
            'latitude' => -1.2850,
            'longitude' => 36.8200,
            'location_name' => 'CBD Branch',
            'speed' => 20.0,
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('vehicle.location.name', 'CBD Branch');

        // 3. Retrieve location via /api/integration/vehicles/{vehicle}/location
        $getRes = $this->getJson("/api/integration/vehicles/{$vehicleId}/location");
        $getRes->assertStatus(200)
            ->assertJsonPath('data.vehicle_id', $vehicleId)
            ->assertJsonPath('data.location.name', 'CBD Branch')
            ->assertJsonPath('data.plate_number', 'KDH 100P');
    }
}
