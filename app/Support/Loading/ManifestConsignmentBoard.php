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
    public const FILTERS = ['all', 'ready', 'not_ready', 'complete'];

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
        $notInBay = fn (Builder $query) => $query
            ->doesntHave('manifestItem')
            ->where(fn (Builder $where) => $where
                ->whereNull('current_location_id')
                ->when($bay, fn (Builder $q) => $q->orWhere('current_location_id', '!=', $bay?->getKey())));

        $query = Consignment::query()
            ->whereHas('handlingUnits', $onThisTrailer)
            ->withCount([
                'handlingUnits as units_count',
                'handlingUnits as on_trailer_count' => $onThisTrailer,
                'handlingUnits as other_trailer_count' => $onOtherTrailer,
                'handlingUnits as in_bay_count' => $inBay,
                'handlingUnits as not_in_bay_count' => $notInBay,
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
                ->having('in_bay_count', '>', 0),
            'not_ready' => $query
                ->having('on_trailer_count', '<', DB::raw('item_count'))
                ->havingRaw("{$notInBayTotal} > 0"),
            default => null,
        };

        $page = $query->paginate($perPage);

        $flags = HandlingUnit::query()
            ->whereIn('consignment_id', $page->getCollection()->modelKeys())
            ->where(fn (Builder $where) => $where->whereNotNull('dg_class')->orWhere('is_food', true))
            ->get(['consignment_id', 'dg_class', 'is_food'])
            ->groupBy('consignment_id');

        $rows = $page->through(function (Consignment $consignment) use ($flags, $bay): array {
            $unitFlags = $flags->get($consignment->getKey(), collect());
            $missing = max($consignment->item_count - (int) $consignment->getAttribute('units_count'), 0);
            $notInBayCount = (int) $consignment->getAttribute('not_in_bay_count') + $missing;
            $inBayCount = (int) $consignment->getAttribute('in_bay_count');
            $onTrailer = (int) $consignment->getAttribute('on_trailer_count');

            return [
                'id' => $consignment->getKey(),
                'connote_number' => $consignment->connote_number,
                'sender_name' => $consignment->getAttribute('sender_name'),
                'receiver_name' => $consignment->getAttribute('receiver_name'),
                'service_code' => $consignment->getAttribute('service_code'),
                'item_count' => $consignment->item_count,
                'on_trailer' => $onTrailer,
                'on_other_trailers' => (int) $consignment->getAttribute('other_trailer_count'),
                'in_bay' => $inBayCount,
                'not_in_bay' => $notInBayCount,
                'bay_code' => $bay?->code,
                'state' => match (true) {
                    $onTrailer >= $consignment->item_count => 'complete',
                    $notInBayCount > 0 => 'not_ready',
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

            [$state, $label] = match (true) {
                $item !== null && $item->manifest_id === $manifest->getKey() => ['this_trailer', 'Loaded on this trailer'],
                $item !== null => ['other_trailer', 'Loaded on '.$item->manifest?->manifest_number],
                $bay !== null && $unit->current_location_id === $bay->getKey() => ['in_bay', 'In the '.$bay->code.' bay'],
                $unit->currentLocation !== null => ['elsewhere', 'In '.$unit->currentLocation->code],
                $unit->current_status->value === 'received' => ['elsewhere', 'Scanned in, location not recorded'],
                default => ['not_scanned_in', 'Not scanned in yet'],
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
                        'location_code' => $event->metadata['location_code'] ?? null,
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
