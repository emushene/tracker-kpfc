<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shop;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake OSRM HTTP requests to keep tests hermetic and fast
        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 15000,
                        'duration' => 1200,
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * Test listing all fleet vehicles with their home shop and live location.
     */
    public function test_can_list_vehicles(): void
    {
        $shop = Shop::create([
            'name' => 'Nairobi Branch',
            'code' => 'KPFC-001',
            'address' => 'Nairobi HQ',
            'latitude' => -1.286389,
            'longitude' => 36.817223,
            'radius_meters' => 500,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '123456789012345',
            'plate_number' => 'KDD 123A',
            'device_name' => 'Van 01',
            'active' => true,
            'assigned_shop_id' => $shop->id,
            'location_name' => 'Nairobi Central',
            'location_latitude' => -1.286389,
            'location_longitude' => 36.817223,
        ]);

        VehiclePosition::create([
            'vehicle_id' => $vehicle->id,
            'latitude' => -1.286389,
            'longitude' => 36.817223,
            'speed' => 35.5,
            'course' => 180.0,
            'acc_status' => 1,
            'gps_time' => now()->timestamp,
        ]);

        $response = $this->getJson('/api/vehicles');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.plate_number', 'KDD 123A')
            ->assertJsonPath('data.0.assigned_shop.name', 'Nairobi Branch')
            ->assertJsonPath('data.0.status', 'moving')
            ->assertJsonPath('data.0.location_name', 'Nairobi Central');
    }

    /**
     * Test filtering vehicles by home shop and search keywords.
     */
    public function test_can_filter_and_search_vehicles(): void
    {
        $shopA = Shop::create([
            'name' => 'Shop Alpha',
            'latitude' => -1.28,
            'longitude' => 36.81,
            'active' => true,
        ]);

        $shopB = Shop::create([
            'name' => 'Shop Beta',
            'latitude' => -1.30,
            'longitude' => 36.85,
            'active' => true,
        ]);

        Vehicle::create([
            'imei' => '111111111111111',
            'plate_number' => 'KCA 111A',
            'assigned_shop_id' => $shopA->id,
            'active' => true,
        ]);

        Vehicle::create([
            'imei' => '222222222222222',
            'plate_number' => 'KCB 222B',
            'assigned_shop_id' => $shopB->id,
            'active' => true,
        ]);

        // Filter by shop_id
        $shopFilterResponse = $this->getJson("/api/vehicles?shop_id={$shopA->id}");
        $shopFilterResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plate_number', 'KCA 111A');

        // Search by plate number
        $searchResponse = $this->getJson('/api/vehicles?search=KCB');
        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plate_number', 'KCB 222B');
    }

    /**
     * Test assigning a permanent home shop to a vehicle.
     */
    public function test_can_assign_home_shop_to_vehicle(): void
    {
        $shop = Shop::create([
            'name' => 'Eldoret Hub',
            'latitude' => 0.522291,
            'longitude' => 35.253833,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '333333333333333',
            'plate_number' => 'KCC 333C',
            'active' => true,
        ]);

        $response = $this->patchJson("/api/vehicles/{$vehicle->id}/assign-shop", [
            'shop_id' => $shop->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('vehicle.assigned_shop_id', $shop->id)
            ->assertJsonPath('vehicle.assigned_shop.name', 'Eldoret Hub');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'assigned_shop_id' => $shop->id,
        ]);
    }

    /**
     * Test clearing/unassigning a vehicle's home shop.
     */
    public function test_can_unassign_home_shop_from_vehicle(): void
    {
        $shop = Shop::create([
            'name' => 'Bomet Branch',
            'latitude' => -0.778704,
            'longitude' => 35.340683,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '444444444444444',
            'plate_number' => 'KCD 444D',
            'assigned_shop_id' => $shop->id,
            'active' => true,
        ]);

        $response = $this->patchJson("/api/vehicles/{$vehicle->id}/assign-shop", [
            'shop_id' => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('vehicle.assigned_shop_id', null);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'assigned_shop_id' => null,
        ]);
    }

    /**
     * Test assigning an inactive shop fails validation.
     */
    public function test_cannot_assign_inactive_shop(): void
    {
        $inactiveShop = Shop::create([
            'name' => 'Closed Depot',
            'latitude' => -1.0,
            'longitude' => 37.0,
            'active' => false,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '555555555555555',
            'plate_number' => 'KCE 555E',
            'active' => true,
        ]);

        $response = $this->patchJson("/api/vehicles/{$vehicle->id}/assign-shop", [
            'shop_id' => $inactiveShop->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shop_id']);
    }

    /**
     * Test dispatching a vehicle on a deployment to a shop.
     */
    public function test_can_dispatch_deployment_to_shop(): void
    {
        $shop = Shop::create([
            'name' => 'Gilgil Branch',
            'latitude' => -0.497628,
            'longitude' => 36.319689,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '666666666666666',
            'plate_number' => 'KCF 666F',
            'active' => true,
        ]);

        $response = $this->postJson("/api/vehicles/{$vehicle->id}/deployments", [
            'destination_type' => 'shop',
            'destination_id' => $shop->id,
            'purpose' => 'Goods Delivery',
            'notes' => 'Urgent morning dispatch',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('deployment.destination_type', 'shop')
            ->assertJsonPath('deployment.destination_id', $shop->id)
            ->assertJsonPath('deployment.status', 'dispatched')
            ->assertJsonPath('deployment.purpose', 'Goods Delivery');

        $this->assertDatabaseHas('vehicle_deployments', [
            'vehicle_id' => $vehicle->id,
            'destination_type' => 'shop',
            'destination_id' => $shop->id,
            'status' => 'dispatched',
        ]);
    }

    /**
     * Test dispatching a vehicle to a custom location.
     */
    public function test_can_dispatch_deployment_to_custom_location(): void
    {
        $location = Location::create([
            'name' => 'Customer Distribution Center',
            'type' => 'client',
            'address' => 'Industrial Area, Nairobi',
            'latitude' => -1.312,
            'longitude' => 36.852,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '777777777777777',
            'plate_number' => 'KCG 777G',
            'active' => true,
        ]);

        $response = $this->postJson("/api/vehicles/{$vehicle->id}/deployments", [
            'destination_type' => 'location',
            'destination_id' => $location->id,
            'purpose' => 'Bulk Restock',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('deployment.destination_type', 'location')
            ->assertJsonPath('deployment.destination_id', $location->id)
            ->assertJsonPath('deployment.status', 'dispatched');
    }

    /**
     * Test that dispatching a second active deployment to the same vehicle is rejected.
     */
    public function test_cannot_dispatch_second_active_deployment(): void
    {
        $shop = Shop::create([
            'name' => 'Nakuru Branch',
            'latitude' => -0.283,
            'longitude' => 36.066,
            'active' => true,
        ]);

        $vehicle = Vehicle::create([
            'imei' => '888888888888888',
            'plate_number' => 'KCH 888H',
            'active' => true,
        ]);

        // Dispatch first deployment
        $this->postJson("/api/vehicles/{$vehicle->id}/deployments", [
            'destination_type' => 'shop',
            'destination_id' => $shop->id,
            'purpose' => 'First Mission',
        ])->assertStatus(201);

        // Attempting to dispatch a second active deployment
        $secondResponse = $this->postJson("/api/vehicles/{$vehicle->id}/deployments", [
            'destination_type' => 'shop',
            'destination_id' => $shop->id,
            'purpose' => 'Second Mission',
        ]);

        $secondResponse->assertStatus(422)
            ->assertJsonValidationErrors(['vehicle']);
    }

    /**
     * Test dispatching to an invalid destination type is rejected.
     */
    public function test_cannot_dispatch_to_invalid_destination_type(): void
    {
        $vehicle = Vehicle::create([
            'imei' => '999999999999999',
            'plate_number' => 'KCI 999I',
            'active' => true,
        ]);

        $response = $this->postJson("/api/vehicles/{$vehicle->id}/deployments", [
            'destination_type' => 'invalid_type',
            'destination_id' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_type']);
    }
}
