<?php

namespace App\Exceptions\Loading;

use App\Models\Manifest;
use RuntimeException;

class ManifestNotReadyToClose extends RuntimeException
{
    public function __construct(
        public readonly Manifest $manifest,
        public readonly int $consignmentsTotal,
        public readonly int $consignmentsComplete,
        public readonly bool $loadingFinished,
    ) {
        $outstanding = $consignmentsTotal - $consignmentsComplete;

        $reasons = array_filter([
            $outstanding > 0 ? "{$outstanding} of {$consignmentsTotal} consignments still outstanding" : null,
            $loadingFinished ? null : 'loading was never marked finished',
        ]);

        parent::__construct(
            "Manifest {$manifest->manifest_number}: ".implode(', ', $reasons).'.',
        );
    }
}
