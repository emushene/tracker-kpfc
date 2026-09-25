<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleRepair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleRepair>
 */
class VehicleRepairFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'maintenance_job_card_id' => null,
            'mechanic_external_user_id' => $this->faker->optional()->uuid(),
            'problem' => $this->faker->sentence(),
            'diagnosis' => $this->faker->optional()->sentence(),
            'repair_performed' => $this->faker->paragraph(),
            'mileage_at_repair' => $this->faker->optional()->numberBetween(1000, 200000),
            'repaired_at' => $this->faker->date(),
            'cost_reference' => $this->faker->optional()->bothify('REF-####??'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
