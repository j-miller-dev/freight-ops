<?php

namespace App\Exceptions\Loading;

use App\Models\HandlingUnit;
use App\Models\Manifest;
use RuntimeException;

class FoodDgClash extends RuntimeException
{
    public function __construct(
        public readonly HandlingUnit $handlingUnit,
        public readonly Manifest $selectedManifest,
        /** @var list<string> */
        public readonly array $conflictingClasses,
    ) {
        $classes = implode(', ', $conflictingClasses);

        $reason = $handlingUnit->is_food
            ? "this food pallet would share manifest {$selectedManifest->manifest_number} with DG class {$classes} already loaded"
            : "this DG class {$handlingUnit->dg_class} pallet would share manifest {$selectedManifest->manifest_number} with food already loaded";

        parent::__construct("Pallet {$handlingUnit->barcode}: {$reason}.");
    }
}
