<?php

namespace Database\Factories;

use App\Models\Depot;
use App\Models\Manifest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manifest>
 */
class ManifestFactory extends Factory
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
            'source' => 'fixture',
            'external_id' => fake()->unique()->uuid(),
            'manifest_number' => fake()->unique()->numerify('#########'),
            'service_date' => today(),
            'status' => 'open',
            'trailer_label' => fake()->optional()->bothify('FreightOps #'),
            'trailer_registration' => null,
            'source_updated_at' => now(),
            'last_synced_at' => now(),
        ];
    }
}
