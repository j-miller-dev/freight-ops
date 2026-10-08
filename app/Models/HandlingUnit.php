<?php

namespace App\Models;

use App\Enums\HandlingUnitStatus;
use Database\Factories\HandlingUnitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property HandlingUnitStatus $current_status
 * @property bool $is_food
 */
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
     * @return BelongsTo<Location, $this>
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /**
     * The pallet's single current manifest assignment, if it has been loaded.
     *
     * @return HasOne<ManifestItem, $this>
     */
    public function manifestItem(): HasOne
    {
        return $this->hasOne(ManifestItem::class);
    }

    /**
     * @return HasMany<OperationalEvent, $this>
     */
    public function operationalEvents(): HasMany
    {
        return $this->hasMany(OperationalEvent::class);
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
            'is_food' => 'boolean',
        ];
    }
}
