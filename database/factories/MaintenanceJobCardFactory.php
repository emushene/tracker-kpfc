<?php

namespace Database\Factories;

use App\Models\MaintenanceJobCard;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceJobCard>
 */
class MaintenanceJobCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_card_number' => 'JC-'.strtoupper($this->faker->bothify('????####')),
            'maintenance_ticket_id' => null,
            'vehicle_id' => Vehicle::factory(),
            'mechanic_external_user_id' => $this->faker->optional()->uuid(),
            'mileage_at_service' => $this->faker->optional()->numberBetween(1000, 200000),
            'reported_problem' => $this->faker->optional()->sentence(),
            'diagnosis' => $this->faker->optional()->sentence(),
            'work_performed' => $this->faker->optional()->paragraph(),
            'notes' => $this->faker->optional()->sentence(),
            'status' => 'open',
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subHours(3),
            'completed_at' => now(),
        ]);
    }
}
