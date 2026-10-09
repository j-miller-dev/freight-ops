<?php

namespace App\Models;

use App\Enums\TrailerType;
use Carbon\CarbonInterface;
use Database\Factories\ManifestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TrailerType $trailer_type
 * @property CarbonInterface|null $departs_at
 * @property CarbonInterface|null $loading_finished_at
 */
class Manifest extends Model
{
    /** @use HasFactory<ManifestFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsToMany<Depot, $this>
     */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(
            Depot::class,
            'manifest_destinations',
            'manifest_id',
            'destination_depot_id',
        )->withPivot('is_primary');
    }

    /**
     * @return BelongsTo<Trailer, $this>
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loading_finished_by');
    }

    /**
     * @return HasMany<ManifestEquipment, $this>
     */
    public function equipment(): HasMany
    {
        return $this->hasMany(ManifestEquipment::class);
    }

    /**
     * @return HasMany<ManifestItem, $this>
     */
    public function manifestItems(): HasMany
    {
        return $this->hasMany(ManifestItem::class);
    }

    /**
     * @param  Builder<Manifest>  $query
     * @return Builder<Manifest>
     */
    public function scopeAvailableForLoading(
        Builder $query,
        Depot $destination,
        CarbonInterface $from,
        CarbonInterface $to,
    ): Builder {
        return $query
            ->where('status', 'open')
            ->whereDate('service_date', '>=', $from->toDateString())
            ->whereDate('service_date', '<=', $to->toDateString())
            ->whereHas(
                'destinations',
                fn (Builder $query) => $query->whereKey($destination->getKey()),
            );
    }

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'departs_at' => 'datetime',
            'loading_finished_at' => 'datetime',
            'trailer_type' => TrailerType::class,
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
