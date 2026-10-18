<?php

namespace App\Support\Unload;

use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\HandlingUnit;

/**
 * How much of a consignment has been placed, so a runner can see when the last
 * piece is down. Pallets in a holding lane are counted apart from the bay: they
 * are placed on purpose, not missing. Anything already loaded on a trailer has
 * left the yard and counts as neither.
 */
class ConsignmentBayProgress
{
    /**
     * @return array{item_count: int, in_bay: int, held: int, all_placed: bool}
     */
    public function for(Consignment $consignment): array
    {
        $counts = HandlingUnit::query()
            ->join('locations', 'locations.id', '=', 'handling_units.current_location_id')
            ->where('handling_units.consignment_id', $consignment->getKey())
            ->whereDoesntHave('manifestItem')
            ->selectRaw('locations.type as location_type, locations.destination_depot_id as destination_depot_id, count(*) as total')
            ->groupBy('locations.type', 'locations.destination_depot_id')
            ->get();

        $inBay = (int) $counts
            ->filter(fn (HandlingUnit $row): bool => $row->getAttribute('location_type') === LocationType::Bay->value
                && $row->getAttribute('destination_depot_id') === $consignment->destination_depot_id)
            ->sum('total');

        $held = (int) $counts
            ->filter(fn (HandlingUnit $row): bool => $row->getAttribute('location_type') === LocationType::HoldingArea->value)
            ->sum('total');

        return [
            'item_count' => $consignment->item_count,
            'in_bay' => $inBay,
            'held' => $held,
            'all_placed' => $inBay + $held >= $consignment->item_count,
        ];
    }
}
