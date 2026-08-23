<?php

namespace App\Models;

use Database\Factories\ManifestItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestItem extends Model
{
    /** @use HasFactory<ManifestItemFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Manifest, $this>
     */
    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

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
    public function loader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    protected function casts(): array
    {
        return [
            'loaded_at' => 'datetime',
        ];
    }
}
