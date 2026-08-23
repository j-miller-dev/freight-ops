<?php

namespace App\Models;

use Database\Factories\ConsignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Consignment extends Model
{
    /** @use HasFactory<ConsignmentFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return HasMany<HandlingUnit, $this>
     */
    public function handlingUnits(): HasMany
    {
        return $this->hasMany(HandlingUnit::class);
    }

    /**
     * @return HasManyThrough<ManifestItem, HandlingUnit, $this>
     */
    public function manifestItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            ManifestItem::class,
            HandlingUnit::class,
            'consignment_id',
            'handling_unit_id',
        );
    }

    public function loadedCount(): int
    {
        return $this->manifestItems()->count();
    }

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
        ];
    }
}
