<?php

namespace App\Exceptions\Loading;

use App\Models\Consignment;
use App\Models\Manifest;
use App\Models\ManifestItem;
use RuntimeException;

class ConsignmentSplit extends RuntimeException
{
    public function __construct(
        public readonly Consignment $consignment,
        public readonly ManifestItem $existingAssignment,
        public readonly Manifest $selectedManifest,
    ) {
        parent::__construct(
            "Consignment {$consignment->connote_number} is already partially loaded on manifest "
            .$existingAssignment->manifest->manifest_number.'.'
        );
    }
}
