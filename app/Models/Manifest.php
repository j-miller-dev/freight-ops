<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ManifestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
            'source_updated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
