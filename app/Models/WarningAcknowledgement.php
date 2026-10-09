<?php

namespace App\Models;

use App\Enums\LoadWarningType;
use Database\Factories\WarningAcknowledgementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarningAcknowledgement extends Model
{
    /** @use HasFactory<WarningAcknowledgementFactory> */
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
     * @return BelongsTo<Manifest, $this>
     */
    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    /**
     * @return BelongsTo<Manifest, $this>
     */
    public function conflictingManifest(): BelongsTo
    {
        return $this->belongsTo(
            Manifest::class,
            'conflicting_manifest_id',
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    protected function casts(): array
    {
        return [
            'warning_type' => LoadWarningType::class,
            'acknowledged_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
