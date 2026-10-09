<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['manifest_id', 'item', 'quantity'])]
class ManifestEquipment extends Model
{
    use HasUuids;

    protected $table = 'manifest_equipment';
}
