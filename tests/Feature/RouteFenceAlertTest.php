<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use App\Services\RouteFenceAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RouteFenceAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_a_route_fence_breach_when_vehicle_moves_more_than_20_meters_off_route(): void
    {
        Log::spy();

        $vehicle = Vehicle::factory()->create([
            'plate_number' => 'KAA-101Z',
            'route_geometry' => [
                [36.0, -1.0],
                [36.001, -1.001],
                [36.002, -1.002],
            ],
            'route_latitude' => -1.0,
            'route_longitude' => 36.0,
        ]);

        $service = app(RouteFenceAlertService::class);

        $triggered = $service->checkAndTrigger(
            $vehicle,
            -0.9975,
            36.0055,
            42.0
        );

        $this->assertTrue($triggered);
        $this->assertDatabaseHas('vehicle_events', [
            'vehicle_id' => $vehicle->id,
            'event_type' => 'route_fence_breach',
        ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Route fence breach detected.', \Mockery::on(function (array $context) use ($vehicle): bool {
                return $context['vehicle_id'] === $vehicle->id
                    && $context['plate_number'] === 'KAA-101Z'
                    && $context['distance_meters'] > 20;
            }));
    }
}
