<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\HandlingUnit;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalEvent>
 */
class OperationalEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_event_id' => fake()->unique()->uuid(),
            'handling_unit_id' => HandlingUnit::factory(),
            'actor_id' => User::factory(),
            'event_type' => EventType::Loaded,
            'occurred_at' => now(),
            'received_at' => now(),
            'metadata' => null,
        ];
    }
}
