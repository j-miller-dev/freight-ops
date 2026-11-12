<?php

namespace App\Exceptions\Loading;

use App\Models\HandlingUnit;
use App\Models\Manifest;
use RuntimeException;

class HandlingUnitNotOnManifest extends RuntimeException
{
    public function __construct(
        public readonly HandlingUnit $handlingUnit,
        public readonly Manifest $manifest,
        public readonly ?Manifest $actualManifest = null,
    ) {
        parent::__construct(
            $actualManifest !== null
                ? "{$handlingUnit->barcode} is on manifest {$actualManifest->manifest_number}, not {$manifest->manifest_number}."
                : "{$handlingUnit->barcode} is not on manifest {$manifest->manifest_number}.",
        );
    }
}
