<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryPart>
 */
class InventoryPartFactory extends Factory
{
    public function definition(): array
    {
        $category = $this->faker->randomElement(['mechanical', 'cosmetic_body']);
        $quantity = $this->faker->numberBetween(5, 100);
        $minimumQuantity = $this->faker->numberBetween(5, 20);

        return [
            'inventory_category_id' => InventoryCategory::factory(),
            'part_number' => strtoupper($this->faker->unique()->bothify('PRT-####-??')),
            'name' => $this->faker->randomElement([
                'Oil Filter', 'Brake Pad Set', 'Air Filter', 'Spark Plug',
                'Fuel Filter', 'Alternator Belt', 'Side Mirror', 'Headlight Assembly',
                'Bumper Cover', 'Wiper Blade Set', 'Radiator Hose',
            ]),
            'category' => $category,
            'description' => $this->faker->optional()->sentence(),
            'quantity' => $quantity,
            'minimum_quantity' => $minimumQuantity,
            'unit_of_measure' => $this->faker->randomElement(['piece', 'set', 'liter', 'box']),
            'location' => 'Shelf '.$this->faker->bothify('?#-##'),
            'unit_cost_reference' => 'REF-'.$this->faker->numerify('#####'),
            'active' => true,
        ];
    }

    public function mechanical(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'mechanical',
            'name' => $this->faker->randomElement(['Oil Filter', 'Brake Pad Set', 'Fuel Filter', 'Alternator Belt']),
        ]);
    }

    public function cosmetic(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'cosmetic_body',
            'name' => $this->faker->randomElement(['Side Mirror', 'Headlight Assembly', 'Bumper Trim', 'Door Handle']),
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 2,
            'minimum_quantity' => 10,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'minimum_quantity' => 5,
        ]);
    }
}
