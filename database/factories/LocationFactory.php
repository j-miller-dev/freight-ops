<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Depot;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'code' => fake()->unique()->bothify('BAY##'),
            'name' => fake()->words(2, true),
            'type' => LocationType::Bay,
            'destination_depot_id' => null,
            'is_active' => true,
        ];
    }
}
