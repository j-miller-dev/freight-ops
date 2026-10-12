<?php

namespace App\Models;

use Database\Factories\HoldingRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Freight from this sender is big, non-urgent stock replenishment and may sit in
 * a holding bay instead of going straight to its destination bay.
 *
 * @property string $sender_name
 * @property int $max_hold_days
 * @property int $base_lane
 * @property string $reason
 */
class HoldingRule extends Model
{
    /** @use HasFactory<HoldingRuleFactory> */
    use HasFactory;

    use HasUuids;

    protected function casts(): array
    {
        return [
            'max_hold_days' => 'integer',
            'base_lane' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
