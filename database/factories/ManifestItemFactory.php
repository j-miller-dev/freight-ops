<?php

namespace Database\Factories;

use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManifestItem>
 */
class ManifestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'manifest_id' => Manifest::factory(),
            'handling_unit_id' => HandlingUnit::factory(),
            'loaded_by' => User::factory(),
            'client_event_id' => fake()->unique()->uuid(),
            'loaded_at' => now(),

        ];
    }
}
