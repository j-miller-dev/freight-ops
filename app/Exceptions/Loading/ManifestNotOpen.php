<?php

namespace App\Exceptions\Loading;

use App\Models\Manifest;
use RuntimeException;

class ManifestNotOpen extends RuntimeException
{
    public function __construct(
        public readonly Manifest $manifest,
    ) {
        parent::__construct(
            "Manifest {$manifest->manifest_number} is not open for loading.",
        );
    }
}
