<?php

namespace App\Models;

use App\Enums\TrailerOwner;
use App\Enums\TrailerType;
use Database\Factories\TrailerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property TrailerOwner $owner
 * @property string|null $operator_name
 * @property TrailerType $default_type
 */
class Trailer extends Model
{
    /** @use HasFactory<TrailerFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return HasMany<Manifest, $this>
     */
    public function manifests(): HasMany
    {
        return $this->hasMany(Manifest::class);
    }

    protected function casts(): array
    {
        return [
            'owner' => TrailerOwner::class,
            'default_type' => TrailerType::class,
            'is_active' => 'boolean',
        ];
    }
}
