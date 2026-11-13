<?php

namespace App\Exceptions\Loading;

use App\Models\HandlingUnit;
use App\Models\Manifest;
use RuntimeException;

class SegregationClash extends RuntimeException
{
    /**
     * @param  list<array<string, mixed>>  $issues  from ManifestSegregation, each with a_label/b_label/status/unit_names
     */
    public function __construct(
        public readonly HandlingUnit $handlingUnit,
        public readonly Manifest $selectedManifest,
        public readonly array $issues,
    ) {
        $first = $issues[0];
        $where = $first['unit_names'] === [] ? '' : ' on unit '.implode(' and ', $first['unit_names']);

        parent::__construct(
            "Pallet {$handlingUnit->barcode}: {$first['a_label']} and {$first['b_label']} must not travel together{$where} ({$first['status']}) on manifest {$selectedManifest->manifest_number}.",
        );
    }
}
