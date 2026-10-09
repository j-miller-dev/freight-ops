<?php

namespace App\Models;

use Database\Factories\ConsignmentHoldBackEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsignmentHoldBackEvent extends Model
{
    /** @use HasFactory<ConsignmentHoldBackEventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Consignment, $this>
     */
    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
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
            'held_back' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
