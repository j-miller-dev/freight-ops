<?php

namespace Database\Factories;

use App\Enums\TrailerOwner;
use App\Enums\TrailerType;
use App\Models\Trailer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trailer>
 */
class TrailerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'F-Ops '.fake()->unique()->numberBetween(1, 999),
            'owner' => TrailerOwner::Own,
            'operator_name' => null,
            'registration' => null,
            'default_type' => TrailerType::BDouble,
            'is_active' => true,
        ];
    }

    public function contractor(string $company = 'Test Haulage'): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $company,
            'owner' => TrailerOwner::Contractor,
            'operator_name' => $company,
        ]);
    }
}
