<?php

namespace App\Exceptions\Loading;

use App\Models\Consignment;
use App\Models\Manifest;
use App\Models\ManifestItem;
use Illuminate\Support\Collection;
use RuntimeException;

class ConsignmentSplit extends RuntimeException
{
    public function __construct(
        public readonly Consignment $consignment,
        /** @var Collection<int, ManifestItem> */
        public readonly Collection $conflictingAssignments,
        public readonly Manifest $selectedManifest,
    ) {
        $summary = $this->conflictsByManifest()
            ->map(fn (array $conflict): string => $conflict['manifest']->manifest_number
                .' ('.$conflict['pallet_count'].' pallets)'
            )
            ->implode(', ');

        parent::__construct(
            "Consignment {$consignment->connote_number} is already partially loaded on manifest "
            .$summary.'.'
        );
    }

    /**
     * @return Collection<string, array{manifest: Manifest, pallet_count: int}>
     */
    public function conflictsByManifest(): Collection
    {
        return $this->conflictingAssignments
            ->groupBy('manifest_id')
            ->map(fn (Collection $assignments): array => [
                'manifest' => $assignments->first()->manifest,
                'pallet_count' => $assignments->count(),
            ]);
    }
}
