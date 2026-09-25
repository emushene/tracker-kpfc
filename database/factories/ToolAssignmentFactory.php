<?php

namespace Database\Factories;

use App\Models\MaintenanceJobCard;
use App\Models\Tool;
use App\Models\ToolAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolAssignment>
 */
class ToolAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $assignedAt = $this->faker->dateTimeBetween('-1 month', 'now');

        return [
            'tool_id' => Tool::factory(),
            'mechanic_external_user_id' => (string) $this->faker->numberBetween(100, 999),
            'maintenance_job_card_id' => MaintenanceJobCard::factory(),
            'assigned_at' => $assignedAt,
            'expected_return_at' => (clone $assignedAt)->modify('+8 hours'),
            'returned_at' => null,
            'assigned_by_external_user_id' => (string) $this->faker->numberBetween(100, 999),
            'received_by_external_user_id' => null,
            'condition_on_return' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => null,
            'received_by_external_user_id' => null,
            'condition_on_return' => null,
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => now(),
            'received_by_external_user_id' => (string) $this->faker->numberBetween(100, 999),
            'condition_on_return' => 'good',
        ]);
    }
}
