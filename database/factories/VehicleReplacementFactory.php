<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleReplacement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleReplacement>
 */
class VehicleReplacementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'maintenance_job_card_id' => null,
            'mechanic_external_user_id' => $this->faker->optional()->uuid(),
            'part_name' => $this->faker->randomElement(['Brake Pad', 'Air Filter', 'Oil Filter', 'Spark Plug', 'Tyre', 'Battery', 'Wiper Blade']),
            'old_part_description' => $this->faker->optional()->sentence(),
            'new_part_description' => $this->faker->optional()->sentence(),
            'reason' => $this->faker->optional()->sentence(),
            'mileage_at_replacement' => $this->faker->optional()->numberBetween(1000, 200000),
            'replaced_at' => $this->faker->date(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
