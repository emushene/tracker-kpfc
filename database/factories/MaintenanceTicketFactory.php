<?php

namespace Database\Factories;

use App\Models\MaintenanceTicket;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTicket>
 */
class MaintenanceTicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-'.strtoupper($this->faker->bothify('????####')),
            'vehicle_id' => Vehicle::factory(),
            'ticket_type' => $this->faker->randomElement(['repair', 'inspection', 'service', 'diagnostic']),
            'status' => 'open',
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'reported_by_external_user_id' => $this->faker->optional()->uuid(),
            'assigned_to_external_user_id' => $this->faker->optional()->uuid(),
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'closed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'closed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'closed_at' => now(),
        ]);
    }
}
