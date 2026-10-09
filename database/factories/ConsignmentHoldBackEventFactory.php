<?php

namespace Database\Factories;

use App\Models\Consignment;
use App\Models\ConsignmentHoldBackEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsignmentHoldBackEvent>
 */
class ConsignmentHoldBackEventFactory extends Factory
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
            'actor_id' => User::factory(),
            'held_back' => true,
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}
