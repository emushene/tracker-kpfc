<?php

namespace Database\Factories;

use App\Models\ChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplate>
 */
class ChecklistTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true).' Checklist',
            'description' => $this->faker->optional()->sentence(),
            'category' => $this->faker->optional()->randomElement(['service', 'inspection', 'brake', 'engine', 'electrical', 'general']),
            'active' => true,
        ];
    }
}
