<?php

namespace App\Support\Loading;

use App\Enums\EventType;
use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Support\Priority\PriorityCalculator;
use App\Support\Priority\PriorityFacts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a loader could put on this trailer right now, most urgent first, with
 * the reason for each. Only freight that is actually ready: sitting in the
 * trailer's bay, not on another trailer, and not held back or in a holding lane.
 */
class NextUp
{
    /** How many to list; the rest is a count. */
    public const LIMIT = 20;

    public function __construct(
        private readonly ManifestConsignmentBoard $board,
        private readonly PriorityCalculator $calculator,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function for(Manifest $manifest): array
    {
        $bay = $this->board->bayFor($manifest);
        $manifestId = $manifest->getKey();
        $meta = [
            'bay_code' => $bay?->code,
            'total' => 0,
            'shown' => 0,
            'held_count' => 0,
            'departs_at' => $manifest->departs_at?->toIso8601String(),
        ];

        if ($bay === null) {
            return ['data' => [], 'meta' => $meta];
        }

        $ready = fn (Builder $query) => $query
            ->doesntHave('manifestItem')
            ->where('current_location_id', $bay->getKey());
        $onThisTrailer = fn (Builder $query) => $query
            ->whereHas('manifestItem', fn (Builder $item) => $item->where('manifest_id', $manifestId));
        $onOtherTrailer = fn (Builder $query) => $query
            ->whereHas('manifestItem', fn (Builder $item) => $item->where('manifest_id', '!=', $manifestId));

        $candidates = Consignment::query()
            ->whereNull('held_back_at')
            ->whereHas('handlingUnits', $ready)
            ->withCount([
                'handlingUnits as ready_count' => $ready,
                'handlingUnits as on_trailer_count' => $onThisTrailer,
                'handlingUnits as other_trailer_count' => $onOtherTrailer,
            ])
            ->get();

        $ids = array_values(array_map('strval', $candidates->modelKeys()));
        $waiting = $this->firstSeen($ids);
        $flags = $this->flags($ids, $bay->getKey());
        $minutesToDeparture = $manifest->departs_at
            ? (int) round(now()->diffInSeconds($manifest->departs_at, false) / 60)
            : null;

        $rows = $candidates
            ->map(function (Consignment $consignment) use ($waiting, $flags, $minutesToDeparture): array {
                $firstSeen = $waiting[$consignment->getKey()] ?? null;
                $priority = $this->calculator->score(new PriorityFacts(
                    serviceCode: $consignment->service_code,
                    minutesToDeparture: $minutesToDeparture,
                    itemCount: $consignment->item_count,
                    onThisTrailer: (int) $consignment->getAttribute('on_trailer_count'),
                    onOtherTrailers: (int) $consignment->getAttribute('other_trailer_count'),
                    minutesWaiting: $firstSeen === null
                        ? null
                        : (int) round(now()->diffInSeconds($firstSeen, true) / 60),
                ));

                return [
                    'id' => $consignment->getKey(),
                    'connote_number' => $consignment->connote_number,
                    'sender_name' => $consignment->sender_name,
                    'service_code' => $consignment->service_code,
                    'item_count' => $consignment->item_count,
                    'ready' => (int) $consignment->getAttribute('ready_count'),
                    'on_trailer' => (int) $consignment->getAttribute('on_trailer_count'),
                    'dg_classes' => $flags[$consignment->getKey()]['dg'] ?? [],
                    'has_food' => $flags[$consignment->getKey()]['food'] ?? false,
                    'score' => $priority->score,
                    'tier' => $priority->tier,
                    'reasons' => $priority->reasons,
                    'waiting_minutes' => $firstSeen === null ? 0 : (int) round(now()->diffInSeconds($firstSeen, true) / 60),
                ];
            })
            ->sort(fn (array $a, array $b): int => [$b['score'], $b['waiting_minutes'], $a['connote_number']]
                <=> [$a['score'], $a['waiting_minutes'], $b['connote_number']])
            ->values();

        $meta['total'] = $rows->count();
        $meta['shown'] = min($rows->count(), self::LIMIT);
        $meta['held_count'] = $this->heldCount($manifest, $ids);

        return [
            'data' => array_values($rows->take(self::LIMIT)->all()),
            'meta' => $meta,
        ];
    }

    /**
     * When each consignment's first piece was scanned into the depot.
     *
     * @param  list<string>  $consignmentIds
     * @return array<string, Carbon>
     */
    private function firstSeen(array $consignmentIds): array
    {
        return DB::table('operational_events')
            ->join('handling_units', 'handling_units.id', '=', 'operational_events.handling_unit_id')
            ->whereIn('handling_units.consignment_id', $consignmentIds)
            ->whereIn('operational_events.event_type', [
                EventType::Received->value,
                EventType::Staged->value,
                EventType::Moved->value,
            ])
            ->groupBy('handling_units.consignment_id')
            ->selectRaw('handling_units.consignment_id as consignment_id, min(operational_events.occurred_at) as first_seen')
            ->pluck('first_seen', 'consignment_id')
            ->map(fn (string $at) => Carbon::parse($at))
            ->all();
    }

    /**
     * Dangerous goods and food among each consignment's ready pieces.
     *
     * @param  list<string>  $consignmentIds
     * @return array<string, array{dg: list<string>, food: bool}>
     */
    private function flags(array $consignmentIds, string $bayId): array
    {
        return HandlingUnit::query()
            ->whereIn('consignment_id', $consignmentIds)
            ->doesntHave('manifestItem')
            ->where('current_location_id', $bayId)
            ->where(fn (Builder $where) => $where->whereNotNull('dg_class')->orWhere('is_food', true))
            ->get(['consignment_id', 'dg_class', 'is_food'])
            ->groupBy('consignment_id')
            ->map(fn ($units): array => [
                'dg' => $units->pluck('dg_class')->filter()->unique()->sort()->values()->all(),
                'food' => $units->contains('is_food', true),
            ])
            ->all();
    }

    /**
     * Freight for this trailer that is deliberately not loadable: held back, or
     * waiting in a holding lane. Anything already in the list is not counted.
     *
     * @param  list<string>  $listedIds
     */
    private function heldCount(Manifest $manifest, array $listedIds): int
    {
        $destinationIds = $manifest->destinations()->pluck('depots.id');

        return Consignment::query()
            ->whereIn('destination_depot_id', $destinationIds)
            ->whereNotIn('id', $listedIds)
            ->where(fn (Builder $held) => $held
                ->where(fn (Builder $backed) => $backed
                    ->whereNotNull('held_back_at')
                    ->whereHas('handlingUnits', fn (Builder $unit) => $unit->doesntHave('manifestItem')))
                ->orWhereHas('handlingUnits', fn (Builder $unit) => $unit
                    ->doesntHave('manifestItem')
                    ->whereHas('currentLocation', fn (Builder $location) => $location
                        ->where('type', LocationType::HoldingArea))))
            ->count();
    }
}
