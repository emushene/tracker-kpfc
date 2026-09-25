<?php

namespace Database\Factories;

use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    public function definition(): array
    {
        $scheduleType = $this->faker->randomElement(['mileage', 'time', 'calendar']);

        return [
            'vehicle_id' => Vehicle::factory(),
            'schedule_type' => $scheduleType,
            'service_name' => $this->faker->randomElement(['Oil Change', 'Tyre Rotation', 'Brake Inspection', 'Full Service', 'Air Filter Replacement']),
            'interval_km' => $scheduleType === 'mileage' ? $this->faker->randomElement([5000, 10000, 15000, 20000]) : null,
            'interval_days' => in_array($scheduleType, ['time', 'calendar']) ? $this->faker->randomElement([30, 60, 90, 180, 365]) : null,
            'last_service_km' => $this->faker->optional()->numberBetween(1000, 80000),
            'last_service_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'next_service_km' => $this->faker->optional()->numberBetween(80001, 120000),
            'next_service_at' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'alert_threshold_km' => $this->faker->optional()->randomElement([500, 1000, 2000]),
            'alert_threshold_days' => $this->faker->optional()->randomElement([7, 14, 30]),
            'notes' => $this->faker->optional()->sentence(),
            'active' => true,
        ];
    }
}
