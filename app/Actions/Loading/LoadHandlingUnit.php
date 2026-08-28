<?php

namespace App\Actions\Loading;

use App\Enums\HandlingUnitStatus;
use App\Enums\LoadWarningType;
use App\Exceptions\Loading\ConsignmentSplit;
use App\Exceptions\Loading\DestinationMismatch;
use App\Exceptions\Loading\HandlingUnitAlreadyAssigned;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use App\Models\WarningAcknowledgement;
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
        array $acknowledgedWarnings = [],
    ): ManifestItem {
        return DB::transaction(function () use (
            $manifest,
            $handlingUnit,
            $loader,
            $clientEventId,
            $occurredAt,
            $acknowledgedWarnings,
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

            $destinationMismatch = ! $lockedManifest->destinations()
                ->whereKey($consignment->destination_depot_id)
                ->exists();

            $destinationMismatchAcknowledged = in_array(
                LoadWarningType::DestinationMismatch,
                $acknowledgedWarnings,
                true,
            );

            if ($destinationMismatch && ! $destinationMismatchAcknowledged) {
                $palletDestination = Depot::query()
                    ->findOrFail($consignment->destination_depot_id);

                throw new DestinationMismatch(
                    palletDestination: $palletDestination,
                    selectedManifest: $lockedManifest,
                );
            }

            $existingConsignmentAssignment = $consignment->manifestItems()
                ->where('manifest_items.manifest_id', '!=', $lockedManifest->getKey())
                ->with('manifest')
                ->first();

            $consignmentSplit = $existingConsignmentAssignment !== null;

            $consignmentSplitAcknowledged = in_array(
                LoadWarningType::ConsignmentSplit,
                $acknowledgedWarnings,
                true,
            );

            if ($consignmentSplit && ! $consignmentSplitAcknowledged) {
                throw new ConsignmentSplit(
                    consignment: $consignment,
                    existingAssignment: $existingConsignmentAssignment,
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

            if ($consignmentSplit) {
                $acknowledgement = new WarningAcknowledgement;
                $acknowledgement->warning_type = LoadWarningType::ConsignmentSplit;
                $acknowledgement->handling_unit_id = $lockedHandlingUnit->getKey();
                $acknowledgement->manifest_id = $lockedManifest->getKey();
                $acknowledgement->conflicting_manifest_id =
                    $existingConsignmentAssignment->manifest_id;
                $acknowledgement->acknowledged_by = $loader->getKey();
                $acknowledgement->client_event_id = $clientEventId;
                $acknowledgement->acknowledged_at = $occurredAt;
                $acknowledgement->metadata = [
                    'consignment_id' => $consignment->getKey(),
                    'connote_number' => $consignment->connote_number,
                    'total_pallet_count' => $consignment->item_count,
                    'loaded_elsewhere_count' => $consignment->manifestItems()
                        ->where(
                            'manifest_items.manifest_id',
                            $existingConsignmentAssignment->manifest_id,
                        )
                        ->count(),
                    'existing_manifest_number' => $existingConsignmentAssignment->manifest->manifest_number,
                ];
                $acknowledgement->save();
            }

            $lockedHandlingUnit->current_status = HandlingUnitStatus::Loaded;
            $lockedHandlingUnit->save();

            if ($destinationMismatch) {
                $acknowledgement = new WarningAcknowledgement;
                $acknowledgement->warning_type = LoadWarningType::DestinationMismatch;
                $acknowledgement->handling_unit_id = $lockedHandlingUnit->getKey();
                $acknowledgement->manifest_id = $lockedManifest->getKey();
                $acknowledgement->acknowledged_by = $loader->getKey();
                $acknowledgement->client_event_id = $clientEventId;
                $acknowledgement->acknowledged_at = $occurredAt;
                $acknowledgement->metadata = [
                    'pallet_destination_id' => $consignment->destination_depot_id,
                ];
                $acknowledgement->save();
            }

            return $manifestItem;
        });
    }
}
