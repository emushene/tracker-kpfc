<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'tool_code' => strtoupper($this->faker->unique()->bothify('TOOL-####-??')),
            'name' => $this->faker->randomElement([
                'OBD-II Diagnostic Scanner', 'Digital Torque Wrench 1/2"',
                'Hydraulic Floor Jack 3T', 'Impact Wrench 18V',
                'Multimeter Digital', 'Brake Bleeder Kit', 'Bearing Puller Set',
            ]),
            'category' => $this->faker->randomElement(['diagnostic', 'torque', 'workshop', 'specialized']),
            'availability_status' => $this->faker->randomElement(['available', 'assigned', 'under_maintenance']),
            'condition' => $this->faker->randomElement(['new', 'good', 'fair']),
            'serial_number' => strtoupper($this->faker->bothify('SN-#####-????')),
            'location' => 'Bay '.$this->faker->bothify('#-Cabinet ?'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => 'available',
        ]);
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => 'assigned',
        ]);
    }

    public function underMaintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => 'under_maintenance',
        ]);
    }
}
