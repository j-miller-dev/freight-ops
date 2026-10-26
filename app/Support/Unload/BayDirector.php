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
    private const LANE_COUNT = 5;

    /**
     * How far a service level moves a pallet toward lane 1 (leaves soonest).
     * Anything not listed stays on the sender's base lane.
     *
     * @var array<string, int>
     */
    private const URGENCY_LANE_SHIFT = [
        'EXPRESS' => -2,
        'ECONOMY' => 1,
    ];

    public function direct(HandlingUnit $handlingUnit): BayDirection
    {
        $consignment = $handlingUnit->consignment;
        $homeDepotId = $this->homeDepotId();

        $rule = $consignment->sender_name === null
            ? null
            : HoldingRule::query()
                ->where('is_active', true)
                ->where('sender_name', $consignment->sender_name)
                ->first();

        if ($rule !== null) {
            $holding = $this->holdingLane(
                $this->activeLocations($homeDepotId, LocationType::HoldingArea),
                $this->targetLane($rule, $consignment->service_code),
            );

            // With nowhere to hold it, fall through to the destination bay.
            if ($holding !== null) {
                return new BayDirection(
                    location: $holding,
                    reason: "{$rule->reason}. Can wait up to {$rule->max_hold_days} "
                        .($rule->max_hold_days === 1 ? 'day' : 'days').'.',
                    isHolding: true,
                    maxHoldDays: $rule->max_hold_days,
                    lane: $holding->lane,
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

    /**
     * Every lane a runner could use when the suggested one is full.
     *
     * @return Collection<int, Location>
     */
    public function holdingLanes(): Collection
    {
        return $this->activeLocations($this->homeDepotId(), LocationType::HoldingArea)
            ->sortBy(fn (Location $location): int => $location->lane ?? 0)
            ->values();
    }

    /**
     * The same direction, but placed in another lane because the suggested one
     * is full. Null when the pallet isn't held or the lane isn't a valid choice.
     */
    public function withLaneOverride(BayDirection $direction, string $locationId): ?BayDirection
    {
        if (! $direction->isHolding) {
            return null;
        }

        $lane = $this->holdingLanes()->firstWhere('id', $locationId);

        if ($lane === null) {
            return null;
        }

        return new BayDirection(
            location: $lane,
            reason: $direction->reason,
            isHolding: true,
            maxHoldDays: $direction->maxHoldDays,
            lane: $lane->lane,
            suggested: $direction->location,
        );
    }

    /**
     * A drop the runner already made while offline: the pallet is physically in
     * that bay or lane, so record it there even though the rules now say
     * otherwise. Null when it isn't an active bay or holding lane at the home depot.
     */
    public function physicalDrop(BayDirection $direction, string $locationId): ?BayDirection
    {
        $location = Location::query()
            ->whereKey($locationId)
            ->where('depot_id', $this->homeDepotId())
            ->whereIn('type', [LocationType::Bay, LocationType::HoldingArea])
            ->where('is_active', true)
            ->first();

        if ($location === null) {
            return null;
        }

        return new BayDirection(
            location: $location,
            reason: "Dropped in {$location->code} while offline. {$direction->reason}",
            isHolding: $location->type === LocationType::HoldingArea,
            lane: $location->lane,
            suggested: $direction->location,
            overrideReason: 'offline_stale_direction',
        );
    }

    private function homeDepotId(): ?string
    {
        return Depot::query()
            ->where('code', config('freight.home_depot_code'))
            ->value('id');
    }

    private function targetLane(HoldingRule $rule, ?string $serviceCode): int
    {
        $shift = self::URGENCY_LANE_SHIFT[strtoupper((string) $serviceCode)] ?? 0;

        return max(1, min(self::LANE_COUNT, $rule->base_lane + $shift));
    }

    /**
     * The lane closest to the target; a holding location with no lane number
     * counts as an exact match so unnumbered setups still work. Ties go to the
     * lower lane because the locations arrive ordered by code.
     *
     * @param  Collection<int, Location>  $holdingLocations
     */
    private function holdingLane(Collection $holdingLocations, int $target): ?Location
    {
        return $holdingLocations
            ->sortBy(fn (Location $location): int => $location->lane === null
                ? 0
                : abs($location->lane - $target))
            ->first();
    }
}
