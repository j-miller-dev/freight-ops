<?php

namespace Database\Factories;

use App\Enums\LoadWarningType;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\User;
use App\Models\WarningAcknowledgement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarningAcknowledgement>
 */
class WarningAcknowledgementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warning_type' => LoadWarningType::DestinationMismatch,
            'handling_unit_id' => HandlingUnit::factory(),
            'manifest_id' => Manifest::factory(),
            'conflicting_manifest_id' => null,
            'acknowledged_by' => User::factory(),
            'client_event_id' => fake()->unique()->uuid(),
            'acknowledged_at' => now(),
            'metadata' => null,
        ];
    }
}
