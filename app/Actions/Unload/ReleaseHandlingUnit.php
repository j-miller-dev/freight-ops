<?php

namespace App\Actions\Unload;

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Exceptions\Unload\HandlingUnitNotHeld;
use App\Models\HandlingUnit;
use App\Models\OperationalEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A supervisor's "move this one now" override on a specific held pallet.
 * Scoped to this physical pallet only -- it does not touch the sender's
 * holding rule, so nothing else from that sender is affected. Releasing
 * doesn't move the pallet itself -- BayDirector just stops sending it back
 * to holding, so the next confirm-drop routes it to its real bay.
 */
class ReleaseHandlingUnit
{
    public function handle(HandlingUnit $handlingUnit, User $releasedBy, CarbonInterface $occurredAt): HandlingUnit
    {
        return DB::transaction(function () use ($handlingUnit, $releasedBy, $occurredAt): HandlingUnit {
            $locked = HandlingUnit::query()
                ->whereKey($handlingUnit->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->current_status !== HandlingUnitStatus::Held) {
                throw new HandlingUnitNotHeld($locked);
            }

            // Already released and still sitting there: nothing new to record.
            if ($locked->released_from_hold_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'released_from_hold_at' => $occurredAt,
                'released_from_hold_by' => $releasedBy->getKey(),
            ])->save();

            $event = new OperationalEvent;
            $event->client_event_id = (string) Str::uuid();
            $event->handling_unit_id = $locked->getKey();
            $event->actor_id = $releasedBy->getKey();
            $event->event_type = EventType::ReleasedFromHold;
            $event->occurred_at = $occurredAt;
            $event->received_at = $occurredAt;
            $event->metadata = [
                'location_id' => $locked->current_location_id,
                'location_code' => $locked->currentLocation?->code,
            ];
            $event->save();

            return $locked;
        });
    }
}
