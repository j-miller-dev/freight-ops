<?php

namespace App\Actions\Loading;

use App\Models\Consignment;
use App\Models\ConsignmentHoldBackEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * An admin-settable override that sits a connote behind other freight even
 * when its service type would otherwise suggest urgency (e.g. routine
 * store-stock replenishment). Deliberately a single manual flag, not a
 * computed priority score.
 */
class SetConsignmentHoldBack
{
    public function handle(
        Consignment $consignment,
        User $actor,
        bool $heldBack,
        ?string $reason,
        CarbonInterface $occurredAt,
    ): Consignment {
        return DB::transaction(function () use ($consignment, $actor, $heldBack, $reason, $occurredAt): Consignment {
            $consignment->held_back_at = $heldBack ? $occurredAt : null;
            $consignment->held_back_by = $heldBack ? $actor->getKey() : null;
            $consignment->held_back_reason = $heldBack ? $reason : null;
            $consignment->save();

            $event = new ConsignmentHoldBackEvent;
            $event->consignment_id = $consignment->getKey();
            $event->actor_id = $actor->getKey();
            $event->held_back = $heldBack;
            $event->reason = $reason;
            $event->occurred_at = $occurredAt;
            $event->save();

            return $consignment;
        });
    }
}
