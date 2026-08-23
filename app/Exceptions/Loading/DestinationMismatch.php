<?php

namespace App\Exceptions\Loading;

use App\Models\Depot;
use App\Models\Manifest;
use RuntimeException;

class DestinationMismatch extends RuntimeException
{
    public function __construct(
        public readonly Depot $palletDestination,
        public readonly Manifest $selectedManifest,
    ) {
        parent::__construct(
            "Pallet destination {$palletDestination->code} is not erved by manifest {$selectedManifest->manifest_number}.",
        );
    }
}
