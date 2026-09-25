<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripStop>
 */
class TripStopFactory extends Factory
{
    protected $model = TripStop::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'sequence' => 1,
            'stop_type' => 'delivery',
            'shop_id' => null,
            'external_shop_id' => (string) fake()->numberBetween(100, 999),
            'location_name' => fake()->city().' Branch',
            'address' => fake()->address(),
            'latitude' => fake()->latitude(-1.4, -1.1),
            'longitude' => fake()->longitude(36.7, 37.0),
            'contact_phone' => fake()->phoneNumber(),
            'delivery_instructions' => 'Deliver behind main receiving dock.',
            'status' => 'pending',
            'arrived_at' => null,
            'departed_at' => null,
        ];
    }

    public function arrived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'arrived',
            'arrived_at' => now()->subMinutes(10),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'arrived_at' => now()->subMinutes(30),
            'departed_at' => now()->subMinutes(5),
        ]);
    }
}
