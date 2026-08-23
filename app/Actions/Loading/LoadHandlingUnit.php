<?php

namespace App\Actions\Loading;

use App\Enums\HandlingUnitStatus;
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
            $lockedHandlingUnit = HandlingUnit::query()
                ->lockForUpdate()
                ->findOrFail($handlingUnit->getKey());

            $existingAssignment = ManifestItem::query()
                ->where('handling_unit_id', $lockedHandlingUnit->getKey())
                ->first();

            if (
                $existingAssignment !== null
                && $existingAssignment->manifest_id === $manifest->getKey()
            ) {
                return $existingAssignment;
            }

            $manifestItem = new ManifestItem;
            $manifestItem->manifest_id = $manifest->getKey();
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
