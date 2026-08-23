<?php

namespace App\Models;

use App\Enums\HandlingUnitStatus;
use Database\Factories\HandlingUnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class HandlingUnit extends Model
{
    /** @use HasFactory<HandlingUnitFactory> */
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
     * @return HasOneThrough<Manifest, ManifestItem, $this>
     */
    public function currentManifest(): HasOneThrough
    {
        return $this->hasOneThrough(
            Manifest::class,
            ManifestItem::class,
            'handling_unit_id',
            'id',
            'id',
            'manifest_id',
        );
    }

    protected function casts(): array
    {
        return [
            'current_status' => HandlingUnitStatus::class,
            'piece_number' => 'integer',
        ];
    }
}
