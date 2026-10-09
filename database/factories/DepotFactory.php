<?php

namespace Database\Factories;

use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depot>
 */
class DepotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('DPT##'),
            'name' => fake()->city().' Depot',
            'timezone' => 'Australia/Melbourne',
            'is_active' => true,
        ];
    }
}
