<?php

namespace App\Models;

use App\Enums\EventType;
use Database\Factories\OperationalEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalEvent extends Model
{
    /** @use HasFactory<OperationalEventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<HandlingUnit, $this>
     */
    public function handlingUnit(): BelongsTo
    {
        return $this->belongsTo(HandlingUnit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
