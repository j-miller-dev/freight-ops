<?php

namespace Database\Factories;

use App\Enums\HandlingUnitStatus;
use App\Models\Consignment;
use App\Models\HandlingUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandlingUnit>
 */
class HandlingUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consignment_id' => Consignment::factory(),
            'barcode' => fake()->unique()->numerify('PALLET-############'),
            'piece_number' => fake()->numberBetween(1, 27),
            'current_status' => HandlingUnitStatus::Pending,
        ];
    }
}
