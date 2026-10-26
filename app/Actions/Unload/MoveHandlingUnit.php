<?php

namespace App\Actions\Unload;

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Exceptions\Unload\HandlingUnitAlreadyLoaded;
use App\Exceptions\Unload\UnloadEventConflict;
use App\Models\HandlingUnit;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use App\Support\Unload\BayDirection;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MoveHandlingUnit
{
    /**
     * Drop a pallet in the bay it was directed to. The current location and the
     * immutable movement event are written in one transaction.
     */
    public function handle(
        HandlingUnit $handlingUnit,
        BayDirection $direction,
        User $runner,
        string $clientEventId,
        CarbonInterface $occurredAt,
    ): HandlingUnit {
        return DB::transaction(function () use (
            $handlingUnit,
            $direction,
            $runner,
            $clientEventId,
            $occurredAt,
        ): HandlingUnit {
            $processedEvent = OperationalEvent::query()
                ->where('client_event_id', $clientEventId)
                ->first();

            if ($processedEvent !== null) {
                if (
                    (string) $processedEvent->handling_unit_id !== (string) $handlingUnit->getKey()
                    || ($processedEvent->metadata['to_location_id'] ?? null) !== $direction->location->getKey()
                ) {
                    throw new UnloadEventConflict($processedEvent);
                }

                return $handlingUnit->refresh();
            }

            $locked = HandlingUnit::query()
                ->whereKey($handlingUnit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->current_status === HandlingUnitStatus::Loaded
                || ManifestItem::query()->where('handling_unit_id', $locked->getKey())->exists()
            ) {
                throw new HandlingUnitAlreadyLoaded($locked);
            }

            // Already there (another runner or an earlier tap): nothing to record.
            if ($locked->current_location_id === $direction->location->getKey()) {
                return $locked;
            }

            $from = $locked->currentLocation;

            $locked->current_location_id = $direction->location->getKey();
            $locked->current_status = $direction->isHolding
                ? HandlingUnitStatus::Held
                : HandlingUnitStatus::Staged;
            $locked->save();

            $event = new OperationalEvent;
            $event->client_event_id = $clientEventId;
            $event->handling_unit_id = $locked->getKey();
            $event->actor_id = $runner->getKey();
            $event->event_type = $from === null ? EventType::Staged : EventType::Moved;
            $event->occurred_at = $occurredAt;
            $event->received_at = now();
            $event->metadata = [
                'from_location_id' => $from?->getKey(),
                'from_location_code' => $from?->code,
                'to_location_id' => $direction->location->getKey(),
                'to_location_code' => $direction->location->code,
                'reason' => $direction->reason,
                'is_holding' => $direction->isHolding,
            ] + ($direction->suggested === null ? [] : [
                'override_reason' => $direction->overrideReason ?? 'lane_full',
                'suggested_location_id' => $direction->suggested->getKey(),
                'suggested_location_code' => $direction->suggested->code,
            ]);
            $event->save();

            return $locked->setRelation('currentLocation', $direction->location);
        });
    }
}
