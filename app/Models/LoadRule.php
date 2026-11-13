<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $category_a
 * @property string $category_b
 * @property string|null $reason
 * @property bool $is_active
 */
class LoadRule extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
