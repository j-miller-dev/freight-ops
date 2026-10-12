<?php

namespace App\Exceptions\Unload;

use App\Models\HandlingUnit;
use RuntimeException;

class HandlingUnitAlreadyLoaded extends RuntimeException
{
    public function __construct(public readonly HandlingUnit $handlingUnit)
    {
        parent::__construct(
            "Pallet {$handlingUnit->barcode} is already on a trailer, so it can't be directed to a bay."
        );
    }
}
