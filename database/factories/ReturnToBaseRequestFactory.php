<?php

namespace Database\Factories;

use App\Models\ReturnToBaseRequest;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnToBaseRequest>
 */
class ReturnToBaseRequestFactory extends Factory
{
    protected $model = ReturnToBaseRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'driver_external_user_id' => (string) fake()->numberBetween(1000, 9999),
            'reason' => 'Engine overheating; unable to proceed to remaining delivery stops.',
            'latitude' => -1.286389,
            'longitude' => 36.817223,
            'current_location_name' => 'Waiyaki Way / Westlands',
            'undelivered_stops' => [
                ['sequence' => 2, 'location_name' => 'Westlands Branch'],
                ['sequence' => 3, 'location_name' => 'Parklands Branch'],
            ],
            'requested_at' => now(),
            'status' => 'pending',
            'decision' => null,
            'decision_maker_external_user_id' => null,
            'decided_at' => null,
            'manager_comments' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'decision' => 'approved',
            'decision_maker_external_user_id' => '1001',
            'decided_at' => now(),
            'manager_comments' => 'Return authorized. Proceed directly to central depot.',
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'denied',
            'decision' => 'denied',
            'decision_maker_external_user_id' => '1001',
            'decided_at' => now(),
            'manager_comments' => 'Mobile maintenance van dispatched to your location.',
        ]);
    }
}
