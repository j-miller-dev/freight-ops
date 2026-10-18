<?php

namespace App\Support\Loading;

use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\HandlingUnit;
use App\Models\Location;
use App\Models\Manifest;
use App\Models\OperationalEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The consignments on a trailer, with enough per-piece accounting to tell a
 * loader where the rest of each order is, so nobody searches the wrong place.
 */
class ManifestConsignmentBoard
{
    public const FILTERS = ['all', 'ready', 'not_ready', 'complete', 'split'];

    /** The bay at this depot that collects freight for the manifest's destination. */
    public function bayFor(Manifest $manifest): ?Location
    {
        $destination = $manifest->destinations()
            ->orderByDesc('manifest_destinations.is_primary')
            ->first(['depots.id']);

        if (! $destination) {
            return null;
        }

        return Location::query()
            ->where('type', LocationType::Bay)
            ->where('is_active', true)
            ->where('destination_depot_id', $destination->getKey())
            ->first();
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, total: int}}
     */
    public function paginate(Manifest $manifest, string $filter, int $perPage): array
    {
        $manifestId = $manifest->getKey();
        $bay = $this->bayFor($manifest);

        $onThisTrailer = fn (Builder $query) => $query
            ->whereHas('manifestItem', fn (Builder $item) => $item->where('manifest_id', $manifestId));
        $onOtherTrailer = fn (Builder $query) => $query
            ->whereHas('manifestItem', fn (Builder $item) => $item->where('manifest_id', '!=', $manifestId));
        $inBay = fn (Builder $query) => $bay
            ? $query->doesntHave('manifestItem')->where('current_location_id', $bay->getKey())
            : $query->whereRaw('1 = 0');
        // Waiting in a holding lane on purpose: placed, but not available to load.
        $held = fn (Builder $query) => $query
            ->doesntHave('manifestItem')
            ->whereHas('currentLocation', fn (Builder $location) => $location->where('type', LocationType::HoldingArea));
        $notInBay = fn (Builder $query) => $query
            ->doesntHave('manifestItem')
            ->where(fn (Builder $where) => $where
                ->whereNull('current_location_id')
                ->orWhereHas('currentLocation', fn (Builder $location) => $location
                    ->where('type', '!=', LocationType::HoldingArea)
                    ->when($bay, fn (Builder $q) => $q->where('id', '!=', $bay?->getKey()))));

        $query = Consignment::query()
            ->whereHas('handlingUnits', $onThisTrailer)
            ->withCount([
                'handlingUnits as units_count',
                'handlingUnits as on_trailer_count' => $onThisTrailer,
                'handlingUnits as other_trailer_count' => $onOtherTrailer,
                'handlingUnits as in_bay_count' => $inBay,
                'handlingUnits as not_in_bay_count' => $notInBay,
                'handlingUnits as held_count' => $held,
            ])
            // Orders with something still to find come first; finished ones sink.
            ->orderByRaw('on_trailer_count >= item_count')
            ->orderBy('connote_number');

        // "Missing" pieces (item_count above the pallets we know of) have not
        // been scanned in, so they count as not in the bay.
        $notInBayTotal = '(not_in_bay_count + greatest(item_count - units_count, 0))';

        match ($filter) {
            'complete' => $query->having('on_trailer_count', '>=', DB::raw('item_count')),
            'ready' => $query
                ->having('on_trailer_count', '<', DB::raw('item_count'))
                ->havingRaw("{$notInBayTotal} = 0")
                ->having('held_count', '=', 0)
                ->having('in_bay_count', '>', 0),
            'not_ready' => $query
                ->having('on_trailer_count', '<', DB::raw('item_count'))
                ->havingRaw("{$notInBayTotal} > 0"),
            'split' => $query->having('other_trailer_count', '>', 0),
            default => null,
        };

        $page = $query->paginate($perPage);
        $consignmentIds = $page->getCollection()->modelKeys();

        // The other manifests each split consignment also sits on, and the
        // holding lanes its held pieces are in, so the row can say where to look.
        $splitManifests = DB::table('manifest_items')
            ->join('handling_units', 'handling_units.id', '=', 'manifest_items.handling_unit_id')
            ->join('manifests', 'manifests.id', '=', 'manifest_items.manifest_id')
            ->whereIn('handling_units.consignment_id', $consignmentIds)
            ->where('manifest_items.manifest_id', '!=', $manifestId)
            ->select('handling_units.consignment_id', 'manifests.manifest_number')
            ->distinct()
            ->orderBy('manifests.manifest_number')
            ->get()
            ->groupBy('consignment_id')
            ->map(fn ($rows): array => $rows->pluck('manifest_number')->all());

        $heldLanes = HandlingUnit::query()
            ->whereIn('consignment_id', $consignmentIds)
            ->tap($held)
            ->with('currentLocation:id,code')
            ->get()
            ->groupBy('consignment_id')
            ->map(fn ($units): array => $units->map(fn (HandlingUnit $unit): ?string => $unit->currentLocation?->code)
                ->filter()->unique()->sort()->values()->all());

        $flags = HandlingUnit::query()
            ->whereIn('consignment_id', $consignmentIds)
            ->where(fn (Builder $where) => $where->whereNotNull('dg_class')->orWhere('is_food', true))
            ->get(['consignment_id', 'dg_class', 'is_food'])
            ->groupBy('consignment_id');

        $rows = $page->through(function (Consignment $consignment) use ($flags, $bay, $splitManifests, $heldLanes): array {
            $unitFlags = $flags->get($consignment->getKey(), collect());
            $missing = max($consignment->item_count - (int) $consignment->getAttribute('units_count'), 0);
            $notInBayCount = (int) $consignment->getAttribute('not_in_bay_count') + $missing;
            $inBayCount = (int) $consignment->getAttribute('in_bay_count');
            $heldCount = (int) $consignment->getAttribute('held_count');
            $otherTrailers = (int) $consignment->getAttribute('other_trailer_count');
            $onTrailer = (int) $consignment->getAttribute('on_trailer_count');

            return [
                'id' => $consignment->getKey(),
                'connote_number' => $consignment->connote_number,
                'sender_name' => $consignment->getAttribute('sender_name'),
                'receiver_name' => $consignment->getAttribute('receiver_name'),
                'service_code' => $consignment->getAttribute('service_code'),
                'item_count' => $consignment->item_count,
                'on_trailer' => $onTrailer,
                'on_other_trailers' => $otherTrailers,
                'split' => $otherTrailers > 0,
                'split_manifests' => $splitManifests->get($consignment->getKey(), []),
                'in_bay' => $inBayCount,
                'held' => $heldCount,
                'held_lanes' => $heldLanes->get($consignment->getKey(), []),
                'not_in_bay' => $notInBayCount,
                'bay_code' => $bay?->code,
                'state' => match (true) {
                    $onTrailer >= $consignment->item_count => 'complete',
                    $notInBayCount > 0 => 'not_ready',
                    $heldCount > 0 => 'held',
                    $inBayCount > 0 => 'ready',
                    default => 'elsewhere',
                },
                'dg_classes' => $unitFlags->pluck('dg_class')->filter()->unique()->sort()->values()->all(),
                'has_food' => $unitFlags->contains('is_food', true),
                'held_back' => $consignment->isHeldBack(),
            ];
        });

        return [
            'data' => array_values($rows->items()),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ];
    }

    /**
     * Every piece of one consignment, where it is relative to this trailer, and
     * its movement history.
     *
     * @return array<string, mixed>
     */
    public function detail(Manifest $manifest, Consignment $consignment): array
    {
        $bay = $this->bayFor($manifest);

        $units = $consignment->handlingUnits()
            ->with(['currentLocation', 'manifestItem.manifest'])
            ->orderBy('piece_number')
            ->get();

        $events = OperationalEvent::query()
            ->whereIn('handling_unit_id', $units->modelKeys())
            ->with('actor:id,name')
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('handling_unit_id');

        $pieces = $units->map(function (HandlingUnit $unit) use ($manifest, $bay, $events): array {
            $item = $unit->manifestItem;

            // A pallet has "arrived" once a cuber or runner has scanned it; before
            // that the depot has no record of it.
            [$state, $label] = match (true) {
                $item !== null && $item->manifest_id === $manifest->getKey() => ['this_trailer', 'On this trailer'],
                $item !== null => ['other_trailer', 'On '.$item->manifest?->manifest_number],
                $unit->currentLocation?->type === LocationType::HoldingArea => ['held', 'Held in '.$unit->currentLocation->code],
                $bay !== null && $unit->current_location_id === $bay->getKey() => ['in_bay', 'In the '.$bay->code.' bay'],
                $unit->currentLocation !== null => ['elsewhere', 'Arrived, in '.$unit->currentLocation->code],
                $unit->current_status->value === 'received' => ['elsewhere', 'Arrived, not in bay'],
                default => ['not_scanned_in', 'Not arrived yet'],
            };

            return [
                'id' => $unit->getKey(),
                'barcode' => $unit->barcode,
                'piece_number' => $unit->piece_number,
                'weight_kg' => $unit->getAttribute('weight_kg'),
                'dg_class' => $unit->getAttribute('dg_class'),
                'un_number' => $unit->getAttribute('un_number'),
                'proper_shipping_name' => $unit->getAttribute('proper_shipping_name'),
                'is_food' => $unit->is_food,
                'where' => ['state' => $state, 'label' => $label],
                'events' => $events->get($unit->getKey(), collect())
                    ->map(fn (OperationalEvent $event): array => [
                        'type' => $event->event_type->value,
                        'occurred_at' => $event->occurred_at->toISOString(),
                        'actor' => $event->actor->name,
                        'location_code' => $event->metadata['location_code'] ?? $event->metadata['to_location_code'] ?? null,
                        'manifest_number' => $event->metadata['manifest_number'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ];
        })->all();

        return [
            'id' => $consignment->getKey(),
            'connote_number' => $consignment->connote_number,
            'sender_name' => $consignment->getAttribute('sender_name'),
            'receiver_name' => $consignment->getAttribute('receiver_name'),
            'item_count' => $consignment->item_count,
            'pieces' => $pieces,
            'held_back' => $consignment->isHeldBack(),
            'held_back_reason' => $consignment->held_back_reason,
            'held_back_by' => $consignment->heldBackBy?->name,
            'held_back_at' => $consignment->held_back_at?->toISOString(),
        ];
    }
}
