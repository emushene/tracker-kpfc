<?php

namespace Tests\Feature;

use App\Models\ReturnToBaseRequest;
use App\Models\Shop;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverApiTest extends TestCase
{
    use RefreshDatabase;

    private User $driverUser;

    private string $driverId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driverUser = User::factory()->create([
            'role' => 'driver',
            'fleet_access' => true,
            'kpfc_sub' => 'drv-9001',
        ]);

        $this->driverId = 'drv-9001';
    }

    public function test_driver_can_get_active_trip(): void
    {
        $vehicle = Vehicle::factory()->create(['plate_number' => 'KDG 456Z']);

        $activeTrip = Trip::factory()->inProgress()->create([
            'vehicle_id' => $vehicle->id,
            'driver_external_user_id' => $this->driverId,
            'trip_number' => 'TRP-ACTIVE-1',
        ]);

        TripStop::factory()->create([
            'trip_id' => $activeTrip->id,
            'sequence' => 1,
            'location_name' => 'Westlands Hub',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson(route('api.driver.trips.active'));

        $response->assertOk()
            ->assertJsonPath('data.id', $activeTrip->id)
            ->assertJsonPath('data.trip_number', 'TRP-ACTIVE-1')
            ->assertJsonPath('data.vehicle.plate_number', 'KDG 456Z')
            ->assertJsonCount(1, 'data.stops');
    }

    public function test_driver_can_get_upcoming_trips(): void
    {
        Trip::factory()->create([
            'driver_external_user_id' => $this->driverId,
            'status' => 'planned',
            'trip_number' => 'TRP-UPCOMING-1',
            'planned_start' => now()->addDay(),
        ]);

        Trip::factory()->create([
            'driver_external_user_id' => $this->driverId,
            'status' => 'planned',
            'trip_number' => 'TRP-UPCOMING-2',
            'planned_start' => now()->addDays(2),
        ]);

        // Trip for another driver should not be returned
        Trip::factory()->create([
            'driver_external_user_id' => 'drv-OTHER',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson(route('api.driver.trips.upcoming'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.trip_number', 'TRP-UPCOMING-1')
            ->assertJsonPath('data.1.trip_number', 'TRP-UPCOMING-2');
    }

    public function test_driver_can_get_assigned_vehicle(): void
    {
        $shop = Shop::create([
            'name' => 'Nairobi Depot',
            'code' => 'NBO-01',
            'address' => 'Industrial Area',
            'latitude' => -1.286389,
            'longitude' => 36.817223,
            'radius_meters' => 500,
            'active' => true,
        ]);

        $vehicle = Vehicle::factory()->create([
            'plate_number' => 'KDG 999A',
            'assigned_shop_id' => $shop->id,
        ]);

        Trip::factory()->inProgress()->create([
            'vehicle_id' => $vehicle->id,
            'driver_external_user_id' => $this->driverId,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson(route('api.driver.assigned-vehicle'));

        $response->assertOk()
            ->assertJsonPath('data.plate_number', 'KDG 999A')
            ->assertJsonPath('status', 'active_trip');
    }

    public function test_driver_can_manually_start_trip_recording_start_details(): void
    {
        $vehicle = Vehicle::factory()->create();

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_external_user_id' => $this->driverId,
            'status' => 'planned',
            'actual_start' => null,
            'starting_mileage' => null,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson(route('api.driver.trips.start', $trip), [
                'starting_mileage' => 45050,
                'start_latitude' => -1.286389,
                'start_longitude' => 36.817223,
                'start_location_name' => 'Central Depot Gate',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Trip started successfully.')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.starting_mileage', 45050)
            ->assertJsonPath('data.start_location_name', 'Central Depot Gate');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => 'in_progress',
            'starting_mileage' => 45050,
        ]);
    }

    public function test_driver_cannot_start_already_active_trip(): void
    {
        $trip = Trip::factory()->inProgress()->create([
            'driver_external_user_id' => $this->driverId,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson(route('api.driver.trips.start', $trip));

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Trip cannot be started because it is already in_progress.']);
    }

    public function test_driver_can_get_ordered_stops_for_current_trip(): void
    {
        $trip = Trip::factory()->inProgress()->create([
            'driver_external_user_id' => $this->driverId,
        ]);

        $stop2 = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'sequence' => 2,
            'location_name' => 'Second Stop',
        ]);

        $stop1 = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'sequence' => 1,
            'location_name' => 'First Stop',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->getJson(route('api.driver.trips.stops', $trip));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $stop1->id)
            ->assertJsonPath('data.1.id', $stop2->id);
    }

    public function test_driver_can_mark_stop_as_arrived_sequentially(): void
    {
        $trip = Trip::factory()->inProgress()->create([
            'driver_external_user_id' => $this->driverId,
        ]);

        $stop1 = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'sequence' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson(route('api.driver.stops.arrive', $stop1));

        $response->assertOk()
            ->assertJsonPath('message', 'Stop marked as arrived successfully.')
            ->assertJsonPath('data.status', 'arrived');

        $this->assertDatabaseHas('trip_stops', [
            'id' => $stop1->id,
            'status' => 'arrived',
        ]);
    }

    public function test_driver_cannot_arrive_at_stop_out_of_sequence(): void
    {
        $trip = Trip::factory()->inProgress()->create([
            'driver_external_user_id' => $this->driverId,
        ]);

        // Stop 1 remains pending
        TripStop::factory()->create([
            'trip_id' => $trip->id,
            'sequence' => 1,
            'status' => 'pending',
        ]);

        // Attempting to arrive at Stop 2 before Stop 1
        $stop2 = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'sequence' => 2,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson(route('api.driver.stops.arrive', $stop2));

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Sequential stop order violation: preceding stops must be completed or marked arrived first.',
            ]);

        $this->assertDatabaseHas('trip_stops', [
            'id' => $stop2->id,
            'status' => 'pending',
        ]);
    }

    public function test_driver_can_submit_return_to_base_request(): void
    {
        $trip = Trip::factory()->inProgress()->create([
            'driver_external_user_id' => $this->driverId,
        ]);

        TripStop::factory()->completed()->create([
            'trip_id' => $trip->id,
            'sequence' => 1,
            'location_name' => 'Stop 1 Completed',
        ]);

        $stop2 = TripStop::factory()->create([
            'trip_id' => $trip->id,
            'sequence' => 2,
            'location_name' => 'Stop 2 Pending Delivery',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson(route('api.driver.trips.return-to-base', $trip), [
                'reason' => 'Radiator leak detected on highway; vehicle cannot safely continue.',
                'latitude' => -1.275000,
                'longitude' => 36.800000,
                'current_location_name' => 'Near Westlands Flyover',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Return-to-base request submitted successfully.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.driver_external_user_id', $this->driverId);

        $this->assertDatabaseHas('return_to_base_requests', [
            'trip_id' => $trip->id,
            'driver_external_user_id' => $this->driverId,
            'status' => 'pending',
        ]);
    }

    public function test_fleet_manager_can_review_and_approve_return_to_base_request(): void
    {
        $manager = User::factory()->manager()->create([
            'kpfc_sub' => 'mgr-500',
        ]);

        $trip = Trip::factory()->inProgress()->create();

        $returnRequest = ReturnToBaseRequest::factory()->create([
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('api.fleet.return-requests.decision', $returnRequest), [
                'decision' => 'approved',
                'manager_comments' => 'Return authorized. Report directly to maintenance bay.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Return-to-base request has been approved.')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.decision_maker_external_user_id', 'mgr-500')
            ->assertJsonPath('data.trip.status', 'returning_to_base');

        $this->assertDatabaseHas('return_to_base_requests', [
            'id' => $returnRequest->id,
            'status' => 'approved',
            'decision' => 'approved',
            'decision_maker_external_user_id' => 'mgr-500',
        ]);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => 'returning_to_base',
        ]);
    }

    public function test_fleet_manager_can_review_and_deny_return_to_base_request(): void
    {
        $manager = User::factory()->manager()->create([
            'kpfc_sub' => 'mgr-501',
        ]);

        $trip = Trip::factory()->inProgress()->create();

        $returnRequest = ReturnToBaseRequest::factory()->create([
            'trip_id' => $trip->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('api.fleet.return-requests.decision', $returnRequest), [
                'decision' => 'denied',
                'manager_comments' => 'Route clearance confirmed; continue to remaining stops.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Return-to-base request has been denied.')
            ->assertJsonPath('data.status', 'denied');

        $this->assertDatabaseHas('return_to_base_requests', [
            'id' => $returnRequest->id,
            'status' => 'denied',
            'decision' => 'denied',
        ]);

        // Trip should still remain in_progress
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_driver_can_complete_trip_recording_mileage_and_updating_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $trip = Trip::factory()->inProgress()->create([
            'vehicle_id' => $vehicle->id,
            'driver_external_user_id' => $this->driverId,
            'starting_mileage' => 80000,
        ]);

        $response = $this->actingAs($this->driverUser)
            ->postJson(route('api.driver.trips.complete', $trip), [
                'ending_mileage' => 80120,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Trip completed successfully.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.ending_mileage', 80120)
            ->assertJsonPath('data.trip_mileage', 120);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => 'completed',
            'ending_mileage' => 80120,
            'trip_mileage' => 120,
        ]);

        // Vehicle cumulative mileage updated in vehicle_mileage
        $this->assertDatabaseHas('vehicle_mileage', [
            'vehicle_id' => $vehicle->id,
            'odometer' => 80120,
        ]);
    }
}
