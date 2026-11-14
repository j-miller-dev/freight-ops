<?php

namespace App\Support\Unload;

use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

/**
 * A guide for runners: how much of each order is already in the bay it is
 * going to, so they can aim to finish the ones that are nearly there and get
 * every consignment into one bay.
 */
class BayConsignmentBoard
{
    /**
     * @return array{
     *     bay: array{code: string, name: string}|null,
     *     current: array<string, mixed>,
     *     others: list<array<string, mixed>>,
     *     complete_count: int,
     *     in_progress_count: int,
     * }
     */
    public function for(Consignment $consignment, int $limit = 12): array
    {
        $destinationId = $consignment->destination_depot_id;

        $bay = Location::query()
            ->where('type', LocationType::Bay)
            ->where('is_active', true)
            ->where('destination_depot_id', $destinationId)
            ->where('depot_id', Depot::query()->where('code', config('freight.home_depot_code'))->value('id'))
            ->first();

        // Consignments with at least one pallet sitting in that bay.
        $inBay = $this->placedCounts($destinationId, LocationType::Bay, $destinationId);
        $consignmentIds = array_unique([...array_keys($inBay), $consignment->getKey()]);

        $held = $this->placedCounts($destinationId, LocationType::HoldingArea, null, $consignmentIds);
        $loaded = $this->loadedCounts($consignmentIds);

        $pills = Consignment::query()
            ->whereIn('id', $consignmentIds)
            ->get(['id', 'connote_number', 'sender_name', 'item_count'])
            ->map(fn (Consignment $row): array => self::pill(
                $row,
                $inBay[$row->getKey()] ?? 0,
                $held[$row->getKey()] ?? 0,
                $loaded[$row->getKey()] ?? 0,
            ));

        $others = $pills
            ->reject(fn (array $pill): bool => $pill['id'] === $consignment->getKey())
            ->filter(fn (array $pill): bool => $pill['in_bay'] > 0 && $pill['out_there'] > 0)
            // Closest to done first: those are the ones worth finishing.
            ->sortBy([
                fn (array $a, array $b): int => $b['placed_share'] <=> $a['placed_share'],
                fn (array $a, array $b): int => $a['out_there'] <=> $b['out_there'],
            ])
            ->take($limit)
            ->values();

        return [
            'bay' => $bay === null ? null : ['code' => $bay->code, 'name' => $bay->name],
            'current' => $pills->firstWhere('id', $consignment->getKey()),
            'others' => $others->all(),
            'complete_count' => $pills
                ->reject(fn (array $pill): bool => $pill['id'] === $consignment->getKey())
                ->filter(fn (array $pill): bool => $pill['in_bay'] > 0 && $pill['out_there'] === 0)
                ->count(),
            'in_progress_count' => $pills
                ->reject(fn (array $pill): bool => $pill['id'] === $consignment->getKey())
                ->filter(fn (array $pill): bool => $pill['in_bay'] > 0 && $pill['out_there'] > 0)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pill(Consignment $consignment, int $inBay, int $held, int $loaded): array
    {
        $total = max(1, $consignment->item_count);
        // Anything already on a trailer has left the yard: it is neither here
        // nor missing, so it does not count as "still out there".
        $outThere = max(0, $consignment->item_count - $inBay - $held - $loaded);

        return [
            'id' => $consignment->getKey(),
            'connote_number' => $consignment->connote_number,
            'sender_name' => $consignment->sender_name,
            'item_count' => $consignment->item_count,
            'in_bay' => $inBay,
            'held' => $held,
            'loaded' => $loaded,
            'out_there' => $outThere,
            'placed_share' => ($inBay + $held) / $total,
        ];
    }

    /**
     * Pallets (not on a trailer) sitting in locations of one type, per consignment.
     *
     * @param  list<string>|null  $consignmentIds  limit to these consignments
     * @return array<string, int>
     */
    private function placedCounts(
        string $destinationId,
        LocationType $type,
        ?string $bayDestinationId,
        ?array $consignmentIds = null,
    ): array {
        return HandlingUnit::query()
            ->join('locations', 'locations.id', '=', 'handling_units.current_location_id')
            ->join('consignments', 'consignments.id', '=', 'handling_units.consignment_id')
            ->where('consignments.destination_depot_id', $destinationId)
            ->where('locations.type', $type)
            ->when($bayDestinationId !== null, fn ($query) => $query->where('locations.destination_depot_id', $bayDestinationId))
            ->when($consignmentIds !== null, fn ($query) => $query->whereIn('handling_units.consignment_id', $consignmentIds))
            ->whereDoesntHave('manifestItem')
            ->groupBy('handling_units.consignment_id')
            ->selectRaw('handling_units.consignment_id as consignment_id, count(*) as total')
            ->pluck('total', 'consignment_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @param  list<string>  $consignmentIds
     * @return array<string, int>
     */
    private function loadedCounts(array $consignmentIds): array
    {
        return DB::table('manifest_items')
            ->join('handling_units', 'handling_units.id', '=', 'manifest_items.handling_unit_id')
            ->whereIn('handling_units.consignment_id', $consignmentIds)
            ->groupBy('handling_units.consignment_id')
            ->selectRaw('handling_units.consignment_id as consignment_id, count(*) as total')
            ->pluck('total', 'consignment_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
