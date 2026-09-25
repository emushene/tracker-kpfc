<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'imei' => $this->faker->unique()->numerify('###############'),
            'plate_number' => strtoupper($this->faker->bothify('??###??')),
            'device_name' => $this->faker->words(2, true),
            'active' => true,
        ];
    }
}
