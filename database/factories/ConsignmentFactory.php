<?php

namespace Database\Factories;

use App\Models\Consignment;
use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consignment>
 */
class ConsignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connote_number' => fake()->unique()->numerify('##########'),
            'destination_depot_id' => Depot::factory(),
            'item_count' => fake()->numberBetween(1, 27),
        ];
    }
}
