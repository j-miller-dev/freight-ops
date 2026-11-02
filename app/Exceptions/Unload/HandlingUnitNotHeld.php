<?php

namespace App\Exceptions\Unload;

use App\Models\HandlingUnit;
use RuntimeException;

class HandlingUnitNotHeld extends RuntimeException
{
    public function __construct(public readonly HandlingUnit $handlingUnit)
    {
        parent::__construct(
            "Pallet {$handlingUnit->barcode} is not currently held, so there is nothing to release."
        );
    }
}
