<?php

namespace App\Exceptions\Loading;

use App\Models\Manifest;
use App\Models\ManifestItem;
use RuntimeException;

class HandlingUnitAlreadyAssigned extends RuntimeException
{
    public function __construct(
        public readonly ManifestItem $existingAssignment,
        public readonly Manifest $selectedManifest,
    ) {
        parent::__construct(
            'The pallet is already assigned to another manifest.',
        );
    }
}
