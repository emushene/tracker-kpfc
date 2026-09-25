<?php

namespace Database\Factories;

use App\Models\InventoryPart;
use App\Models\JobCardPart;
use App\Models\MaintenanceJobCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobCardPart>
 */
class JobCardPartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'maintenance_job_card_id' => MaintenanceJobCard::factory(),
            'inventory_part_id' => InventoryPart::factory(),
            'quantity' => $this->faker->numberBetween(1, 4),
            'notes' => $this->faker->optional()->sentence(),
            'allocated_by_external_user_id' => (string) $this->faker->numberBetween(100, 999),
        ];
    }
}
