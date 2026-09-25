<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_number' => 'TRP-'.fake()->unique()->numerify('#####'),
            'transport_request_id' => null,
            'vehicle_id' => Vehicle::factory(),
            'driver_external_user_id' => (string) fake()->numberBetween(1000, 9999),
            'status' => 'planned',
            'planned_start' => now()->addHour(),
            'actual_start' => null,
            'planned_end' => now()->addHours(5),
            'actual_end' => null,
            'start_latitude' => -1.286389,
            'start_longitude' => 36.817223,
            'start_location_name' => 'Nairobi Central Depot',
            'starting_mileage' => fake()->numberBetween(50000, 100000),
            'ending_mileage' => null,
            'trip_mileage' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'actual_start' => now()->subHours(2),
        ]);
    }

    public function returningToBase(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'returning_to_base',
            'actual_start' => now()->subHours(3),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startMileage = $attributes['starting_mileage'] ?? 50000;
            $tripMileage = fake()->numberBetween(20, 150);

            return [
                'status' => 'completed',
                'actual_start' => now()->subHours(4),
                'actual_end' => now()->subHour(),
                'starting_mileage' => $startMileage,
                'ending_mileage' => $startMileage + $tripMileage,
                'trip_mileage' => $tripMileage,
            ];
        });
    }
}
