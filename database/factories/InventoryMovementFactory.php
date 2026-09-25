<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        $movementType = $this->faker->randomElement(['restock', 'job_card_usage', 'adjustment', 'return']);
        $quantity = $movementType === 'job_card_usage' ? -$this->faker->numberBetween(1, 5) : $this->faker->numberBetween(1, 20);

        return [
            'inventory_part_id' => InventoryPart::factory(),
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'balance_after' => $this->faker->numberBetween(10, 100),
            'reference_type' => $movementType === 'job_card_usage' ? 'maintenance_job_card' : 'purchase_order',
            'reference_id' => $this->faker->optional()->numberBetween(1, 1000),
            'actor_external_user_id' => (string) $this->faker->numberBetween(100, 999),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
