<?php

namespace App\Actions\Loading;

use App\Enums\HandlingUnitStatus;
use App\Exceptions\Loading\DestinationMismatch;
use App\Exceptions\Loading\HandlingUnitAlreadyAssigned;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class LoadHandlingUnit
{
    public function handle(
        Manifest $manifest,
        HandlingUnit $handlingUnit,
        User $loader,
        string $clientEventId,
        CarbonInterface $occurredAt,
    ): ManifestItem {
        return DB::transaction(function () use (
            $manifest,
            $handlingUnit,
            $loader,
            $clientEventId,
            $occurredAt,
        ): ManifestItem {
            $lockedManifest = Manifest::query()
                ->lockForUpdate()
                ->findOrFail($manifest->getKey());

            if ($lockedManifest->status !== 'open') {
                throw new ManifestNotOpen($lockedManifest);
            }
            $lockedHandlingUnit = HandlingUnit::query()
                ->lockForUpdate()
                ->findOrFail($handlingUnit->getKey());

            $existingAssignment = ManifestItem::query()
                ->where('handling_unit_id', $lockedHandlingUnit->getKey())
                ->first();

            if ($existingAssignment !== null) {
                if ($existingAssignment->manifest_id === $lockedManifest->getKey()) {
                    return $existingAssignment;
                }

                throw new HandlingUnitAlreadyAssigned(
                    existingAssignment: $existingAssignment,
                    selectedManifest: $lockedManifest,
                );
            }
            $consignment = $lockedHandlingUnit->consignment()->firstOrFail();

            $servesDestination = $lockedManifest->destinations()
                ->whereKey($consignment->destination_depot_id)
                ->exists();

            if (! $servesDestination) {
                $palletDestination = Depot::query()
                    ->findOrFail($consignment->destination_depot_id);

                throw new DestinationMismatch(
                    palletDestination: $palletDestination,
                    selectedManifest: $lockedManifest,
                );
            }

            $manifestItem = new ManifestItem;
            $manifestItem->manifest_id = $lockedManifest->getKey();
            $manifestItem->handling_unit_id = $lockedHandlingUnit->getKey();
            $manifestItem->loaded_by = $loader->getKey();
            $manifestItem->client_event_id = $clientEventId;
            $manifestItem->loaded_at = $occurredAt;
            $manifestItem->save();

            $lockedHandlingUnit->current_status = HandlingUnitStatus::Loaded;
            $lockedHandlingUnit->save();

            return $manifestItem;
        });
    }
}
