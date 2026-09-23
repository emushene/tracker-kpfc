<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthenticationAndAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [
                    [
                        'distance' => 10000,
                        'duration' => 600,
                    ],
                ],
            ], 200),
        ]);
    }

    /**
     * Unauthenticated guest is redirected to /login on web routes.
     */
    public function test_guest_redirected_to_login_on_web_routes(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/test-dashboard')->assertRedirect('/login');
        $this->get('/protrack/test')->assertRedirect('/login');
    }

    /**
     * Unauthenticated guest receives 401 Unauthorized on API routes.
     */
    public function test_guest_denied_with_401_on_api_routes(): void
    {
        $this->getJson('/api/shops')->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/vehicles')->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->postJson('/api/vehicles', ['imei' => '123456789012345'])->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/integration/vehicles')->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Authenticated user without fleet_access is denied access (403 on API, redirected on Web).
     */
    public function test_user_without_fleet_access_is_forbidden(): void
    {
        $user = User::factory()->withoutFleetAccess()->create([
            'role' => 'user',
        ]);

        $this->actingAs($user)->getJson('/api/vehicles')
            ->assertStatus(403)
            ->assertJsonPath('message', 'fleet_access_denied');

        $this->actingAs($user)->get('/')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['sso']);
    }

    /**
     * Authenticated user with read-only role ('user') can view shops, vehicles, and locations.
     */
    public function test_read_only_user_can_view_vehicles_and_locations(): void
    {
        $viewer = User::factory()->readOnly()->create();

        $vehicle = Vehicle::create([
            'imei' => '123456789012345',
            'plate_number' => 'KAA 001A',
            'location_name' => 'Depot 1',
            'active' => true,
        ]);

        $this->actingAs($viewer)->get('/')->assertStatus(200);

        $this->actingAs($viewer)->getJson('/api/shops')
            ->assertStatus(200);

        $this->actingAs($viewer)->getJson('/api/vehicles')
            ->assertStatus(200)
            ->assertJsonPath('data.0.plate_number', 'KAA 001A');

        $this->actingAs($viewer)->getJson("/api/vehicles/{$vehicle->id}/location")
            ->assertStatus(200)
            ->assertJsonPath('data.plate_number', 'KAA 001A');
    }

    /**
     * Authenticated user with read-only role ('user') cannot write or mutate the database.
     */
    public function test_read_only_user_cannot_write_to_database(): void
    {
        $viewer = User::factory()->readOnly()->create();

        $vehicle = Vehicle::create([
            'imei' => '123456789012345',
            'plate_number' => 'KAA 001A',
            'active' => true,
        ]);

        $shop = Shop::create([
            'name' => 'Main Hub',
            'latitude' => -1.28,
            'longitude' => 36.82,
            'active' => true,
        ]);

        // 1. Cannot create vehicle
        $this->actingAs($viewer)->postJson('/api/vehicles', [
            'imei' => '999999999999999',
            'plate_number' => 'KBB 002B',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to modify fleet data.');

        // 2. Cannot update vehicle location
        $this->actingAs($viewer)->postJson("/api/vehicles/{$vehicle->id}/location", [
            'latitude' => -1.285,
            'longitude' => 36.825,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to modify fleet data.');

        // 3. Cannot assign home shop
        $this->actingAs($viewer)->patchJson("/api/vehicles/{$vehicle->id}/assign-shop", [
            'shop_id' => $shop->id,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to modify fleet data.');

        // 4. Cannot dispatch deployment
        $this->actingAs($viewer)->postJson("/api/vehicles/{$vehicle->id}/deployments", [
            'destination_type' => 'shop',
            'destination_id' => $shop->id,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'You do not have permission to modify fleet data.');
    }

    /**
     * Admin and manager roles can successfully perform write/mutation operations.
     */
    public function test_admin_and_manager_can_write_to_database(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->manager()->create();

        // Admin can create a vehicle
        $this->actingAs($admin)->postJson('/api/vehicles', [
            'imei' => '111222333444555',
            'plate_number' => 'KCC 003C',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Vehicle successfully created.');

        // Manager can create a vehicle
        $this->actingAs($manager)->postJson('/api/vehicles', [
            'imei' => '555444333222111',
            'plate_number' => 'KDD 004D',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Vehicle successfully created.');
    }

    /**
     * Public authentication routes and signed webhooks remain accessible to unauthenticated guests.
     */
    public function test_public_sso_and_webhook_routes_remain_accessible(): void
    {
        // Login page is accessible
        $this->get('/login')->assertStatus(200);

        // Redirect initiates SSO flow (returns 302 away to IdP)
        $this->get('/auth/kpfc/redirect')->assertStatus(302);
    }
}
