<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    /**
     * @return BelongsTo<Depot, $this>
     */
    public function destinationDepot(): BelongsTo
    {
        return $this->belongsTo(Depot::class, 'destination_depot_id');
    }

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }
}
