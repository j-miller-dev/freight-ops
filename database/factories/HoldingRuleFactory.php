<?php

namespace Database\Factories;

use App\Models\HoldingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HoldingRule>
 */
class HoldingRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_name' => fake()->unique()->company(),
            'max_hold_days' => 3,
            'base_lane' => 3,
            'reason' => 'Stock replenishment, not urgent',
            'is_active' => true,
        ];
    }
}
