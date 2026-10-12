<?php

namespace App\Support\Unload;

use App\Enums\LocationType;
use App\Exceptions\Unload\NoBayForDestination;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\HoldingRule;
use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides which bay a pallet goes to. A sender with a holding rule goes to the
 * holding bay; everything else goes to the bay that collects its destination.
 */
class BayDirector
{
    public function direct(HandlingUnit $handlingUnit): BayDirection
    {
        $consignment = $handlingUnit->consignment;
        $homeDepotId = Depot::query()
            ->where('code', config('freight.home_depot_code'))
            ->value('id');

        $rule = $consignment->sender_name === null
            ? null
            : HoldingRule::query()
                ->where('is_active', true)
                ->where('sender_name', $consignment->sender_name)
                ->first();

        if ($rule !== null) {
            $holding = $this->activeLocations($homeDepotId, LocationType::HoldingArea)->first();

            // With nowhere to hold it, fall through to the destination bay.
            if ($holding !== null) {
                return new BayDirection(
                    location: $holding,
                    reason: "{$rule->reason}. Can wait up to {$rule->max_hold_days} "
                        .($rule->max_hold_days === 1 ? 'day' : 'days').'.',
                    isHolding: true,
                    maxHoldDays: $rule->max_hold_days,
                );
            }
        }

        $destination = Depot::query()->findOrFail($consignment->destination_depot_id);

        $bay = $this->activeLocations($homeDepotId, LocationType::Bay)
            ->firstWhere('destination_depot_id', $destination->getKey());

        if ($bay === null) {
            throw new NoBayForDestination($destination);
        }

        return new BayDirection(
            location: $bay,
            reason: "Freight for {$destination->code}",
            isHolding: false,
        );
    }

    /**
     * @return Collection<int, Location>
     */
    private function activeLocations(?string $depotId, LocationType $type)
    {
        return Location::query()
            ->where('depot_id', $depotId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }
}
