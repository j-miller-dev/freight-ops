<?php

namespace App\Actions\Loading;

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Enums\LocationType;
use App\Exceptions\Loading\ClientEventConflict;
use App\Exceptions\Loading\HandlingUnitNotOnManifest;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Models\HandlingUnit;
use App\Models\Location;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Scan a pallet off a trailer: the inverse of LoadHandlingUnit. The pallet
 * goes back to the bay or lane it was last placed in, so a runner can find it.
 */
class RemoveHandlingUnitFromManifest
{
    public function handle(
        Manifest $manifest,
        HandlingUnit $handlingUnit,
        User $actor,
        string $clientEventId,
        CarbonInterface $occurredAt,
    ): HandlingUnit {
        return DB::transaction(function () use (
            $manifest,
            $handlingUnit,
            $actor,
            $clientEventId,
            $occurredAt,
        ): HandlingUnit {
            // A retried request (flaky Wi-Fi) must not take the pallet off twice.
            $processedEvent = OperationalEvent::query()
                ->where('client_event_id', $clientEventId)
                ->first();

            if ($processedEvent !== null) {
                if (
                    (string) $processedEvent->handling_unit_id !== (string) $handlingUnit->getKey()
                    || (string) ($processedEvent->metadata['manifest_id'] ?? null) !== (string) $manifest->getKey()
                    || $processedEvent->event_type !== EventType::Unloaded
                ) {
                    throw new ClientEventConflict(
                        existingEvent: $processedEvent,
                        handlingUnitId: (string) $handlingUnit->getKey(),
                        manifestId: (string) $manifest->getKey(),
                    );
                }

                return $handlingUnit->refresh()->load('currentLocation');
            }

            $lockedManifest = Manifest::query()
                ->lockForUpdate()
                ->findOrFail($manifest->getKey());

            if ($lockedManifest->status !== 'open') {
                throw new ManifestNotOpen($lockedManifest);
            }

            $locked = HandlingUnit::query()
                ->lockForUpdate()
                ->findOrFail($handlingUnit->getKey());

            $assignment = ManifestItem::query()
                ->where('handling_unit_id', $locked->getKey())
                ->lockForUpdate()
                ->first();

            if ($assignment === null || $assignment->manifest_id !== $lockedManifest->getKey()) {
                throw new HandlingUnitNotOnManifest(
                    handlingUnit: $locked,
                    manifest: $lockedManifest,
                    actualManifest: $assignment?->manifest,
                );
            }

            $location = $locked->currentLocation ?? $this->lastKnownLocation($locked);

            $assignment->delete();

            $locked->current_location_id = $location?->getKey();
            $locked->current_status = match (true) {
                $location === null => HandlingUnitStatus::Received,
                $location->type === LocationType::HoldingArea => HandlingUnitStatus::Held,
                default => HandlingUnitStatus::Staged,
            };
            $locked->save();

            $event = new OperationalEvent;
            $event->client_event_id = $clientEventId;
            $event->handling_unit_id = $locked->getKey();
            $event->actor_id = $actor->getKey();
            $event->event_type = EventType::Unloaded;
            $event->occurred_at = $occurredAt;
            $event->received_at = now();
            $event->metadata = [
                'action' => 'scan_off',
                'manifest_id' => $lockedManifest->getKey(),
                'manifest_number' => $lockedManifest->manifest_number,
                'originally_loaded_by' => $assignment->loaded_by,
                'to_location_id' => $location?->getKey(),
                'to_location_code' => $location?->code,
            ];
            $event->save();

            return $locked->setRelation('currentLocation', $location);
        });
    }

    /**
     * Where the pallet was last put down, from its movement history. Runner
     * moves record the location id; older/seeded events only keep its code.
     */
    private function lastKnownLocation(HandlingUnit $handlingUnit): ?Location
    {
        $events = OperationalEvent::query()
            ->where('handling_unit_id', $handlingUnit->getKey())
            ->whereIn('event_type', [EventType::Staged->value, EventType::Moved->value])
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();

        foreach ($events as $event) {
            $metadata = $event->metadata ?? [];

            $location = isset($metadata['to_location_id'])
                ? Location::query()->find($metadata['to_location_id'])
                : (isset($metadata['location_code'])
                    ? Location::query()->where('code', $metadata['location_code'])->first()
                    : null);

            if ($location !== null) {
                return $location;
            }
        }

        return null;
    }
}
