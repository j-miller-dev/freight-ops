<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Enums\LocationType;
use App\Enums\TrailerType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Location;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A busy Sydney-bound day: many manifests (enough to page), and consignments in
 * every load state so split and "what's left" views have real data. Includes
 * food and dangerous goods so the trailer alerts have something to show. All
 * names are fictional.
 */
class SydneyFreightSeeder extends Seeder
{
    private const MANIFEST_COUNT = 7;

    private const CONSIGNMENT_COUNT = 48;

    /** @var list<string> */
    private const SENDERS = [
        'Harbourline Hardware', 'Parkside Building Supplies', 'Northgate Office Products',
        'Evergreen Produce Co', 'Redfern Industrial Chemicals', 'Summit Pool & Garden',
        'Coastal Packaging', 'Ironbark Timber', 'Bluegum Beverages', 'Apex Auto Parts',
    ];

    /** @var list<string> */
    private const RECEIVERS = [
        'Ironbark Timber - Penrith', 'Parkside Trade - Blacktown', 'Harbourline - Mascot',
        'Evergreen Fresh - Homebush', 'Summit Outdoor - Castle Hill', 'Northgate Stationers - Parramatta',
        'Coastal Cafe Supplies - Manly', 'Apex Parts - Silverwater',
    ];

    /** @var list<string> */
    private const SERVICES = ['EXPRESS', 'STANDARD', 'STANDARD', 'ECONOMY'];

    /** Senders whose freight is food, and so must not share a trailer with some DG classes. */
    private const FOOD_SENDERS = ['Evergreen Produce Co', 'Bluegum Beverages'];

    /**
     * Dangerous goods each sender ships: [class, UN number, proper shipping name].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private const DG_BY_SENDER = [
        'Redfern Industrial Chemicals' => [
            ['8', 'UN1791', 'HYPOCHLORITE SOLUTION'],
            ['6.1', 'UN2810', 'TOXIC LIQUID, ORGANIC, N.O.S.'],
            ['3', 'UN1993', 'FLAMMABLE LIQUID, N.O.S.'],
        ],
        'Summit Pool & Garden' => [
            ['5.1', 'UN1748', 'CALCIUM HYPOCHLORITE, DRY'],
            ['8', 'UN1789', 'HYDROCHLORIC ACID'],
        ],
        'Apex Auto Parts' => [
            ['2.1', 'UN1950', 'AEROSOLS, FLAMMABLE'],
            ['9', 'UN3481', 'LITHIUM ION BATTERIES'],
        ],
    ];

    private Location $bay;

    private Location $holding;

    /** @var array<string, int> pallets loaded so far, by manifest id */
    private array $fill = [];

    /** @var array<string, array<string, true>> trailer positions taken, by manifest id */
    private array $slots = [];

    public function run(): void
    {
        $loader = User::query()->where('email', 'test@example.com')->firstOrFail();
        $sydney = Depot::query()->where('code', 'SYD')->firstOrFail();
        $melbourne = Depot::query()->where('code', 'MEL')->firstOrFail();

        // Rebuild this seeder's freight from scratch so a changed plan never
        // leaves stale pallets or assignments behind.
        Consignment::query()->where('connote_number', 'like', 'SY%')->delete();
        $this->fill = [];
        $this->slots = [];

        $this->bay = Location::query()->updateOrCreate(
            ['depot_id' => $melbourne->getKey(), 'code' => 'SYD02'],
            [
                'name' => 'Sydney bay',
                'type' => LocationType::Bay,
                'destination_depot_id' => $sydney->getKey(),
                'is_active' => true,
            ],
        );
        $this->holding = Location::query()->updateOrCreate(
            ['depot_id' => $melbourne->getKey(), 'code' => 'HOLD1'],
            ['name' => 'Holding bay 1', 'type' => LocationType::HoldingArea, 'is_active' => true],
        );

        $manifests = $this->manifests($melbourne, $sydney);

        // Fixed seed so every run produces the same barcodes and load states.
        mt_srand(20261009);

        for ($i = 1; $i <= self::CONSIGNMENT_COUNT; $i++) {
            $this->consignment($i, $sydney, $manifests, $loader);
        }
    }

    /**
     * @return list<Manifest>
     */
    private function manifests(Depot $origin, Depot $destination): array
    {
        // Minutes until each trailer departs; the last one has already left.
        $departures = [25, 100, 180, 300, 420, 540, -120];
        $trailers = [TrailerType::BDouble, TrailerType::BDouble, TrailerType::BTriple, TrailerType::BDouble, TrailerType::ADouble, TrailerType::BDouble, TrailerType::BDouble];
        $manifests = [];

        for ($n = 1; $n <= self::MANIFEST_COUNT; $n++) {
            $manifest = Manifest::query()->updateOrCreate(
                ['source' => 'seed', 'external_id' => "seed-mel-syd-{$n}"],
                [
                    'depot_id' => $origin->getKey(),
                    'manifest_number' => sprintf('MEL-SYD-2610%02d', $n),
                    'service_date' => today()->toDateString(),
                    'departs_at' => now()->addMinutes($departures[$n - 1]),
                    // The last one is already gone, to exercise the closed state.
                    'status' => $n === self::MANIFEST_COUNT ? 'closed' : 'open',
                    'trailer_label' => "SYD FreightOps {$n}",
                    'trailer_type' => $trailers[$n - 1],
                    'source_updated_at' => now(),
                    'last_synced_at' => now(),
                ],
            );

            $manifest->destinations()->syncWithoutDetaching([
                $destination->getKey() => ['is_primary' => true],
            ]);

            $manifests[] = $manifest;
        }

        return $manifests;
    }

    /**
     * @param  list<Manifest>  $manifests
     */
    private function consignment(int $index, Depot $sydney, array $manifests, User $loader): void
    {
        $connote = sprintf('SY%06d', 410000 + $index * 37);
        $itemCount = $this->itemCount($index);
        $sender = self::SENDERS[$index % count(self::SENDERS)];

        $consignment = Consignment::query()->updateOrCreate(
            ['connote_number' => $connote],
            [
                'destination_depot_id' => $sydney->getKey(),
                'item_count' => $itemCount,
                'sender_name' => $sender,
                'receiver_name' => self::RECEIVERS[$index % count(self::RECEIVERS)],
                'service_code' => self::SERVICES[$index % count(self::SERVICES)],
            ],
        );

        // Open manifests only: loaders never build onto a closed one.
        $open = array_values(array_filter($manifests, fn (Manifest $m) => $m->status === 'open'));
        $plan = $this->loadPlan($index, $itemCount, count($open));

        for ($piece = 1; $piece <= $itemCount; $piece++) {
            $manifest = isset($plan[$piece]) ? $this->withRoom($open, $plan[$piece]) : null;

            $this->pallet($consignment, $connote, $piece, $manifest, $loader, $sender, $index);
        }
    }

    /**
     * The wanted manifest, or the next one with a free position. Null when every
     * trailer is full, which leaves the pallet on the dock.
     *
     * @param  list<Manifest>  $open
     */
    private function withRoom(array $open, int $wanted): ?Manifest
    {
        for ($step = 0; $step < count($open); $step++) {
            $manifest = $open[($wanted + $step) % count($open)];
            $capacity = $manifest->trailer_type->capacity();

            if (($this->fill[$manifest->getKey()] ?? 0) < $capacity) {
                $this->fill[$manifest->getKey()] = ($this->fill[$manifest->getKey()] ?? 0) + 1;

                return $manifest;
            }
        }

        return null;
    }

    /** Mostly small orders, with a few big ones that are likely to split. */
    private function itemCount(int $index): int
    {
        return match (true) {
            $index % 11 === 0 => mt_rand(24, 35),
            $index % 5 === 0 => mt_rand(10, 18),
            default => mt_rand(1, 9),
        };
    }

    /**
     * Which open manifest (by position) each piece lands on; pieces missing
     * from the plan are still on the dock.
     *
     * @return array<int, int>
     */
    private function loadPlan(int $index, int $itemCount, int $manifestCount): array
    {
        $first = $index % $manifestCount;
        $second = ($first + 1 + $index % 2) % $manifestCount;
        $plan = [];

        switch ($index % 6) {
            case 0: // untouched
                break;
            case 1: // fully loaded on one trailer
                for ($p = 1; $p <= $itemCount; $p++) {
                    $plan[$p] = $first;
                }
                break;
            case 2: // split across two trailers, all loaded
                for ($p = 1; $p <= $itemCount; $p++) {
                    $plan[$p] = $p <= intdiv($itemCount, 2) ? $first : $second;
                }
                break;
            case 3: // partly loaded, rest on the dock
                for ($p = 1; $p <= max(1, intdiv($itemCount, 2)); $p++) {
                    $plan[$p] = $first;
                }
                break;
            case 4: // a couple on one trailer, a couple on another, rest on the dock
                $plan[1] = $first;
                if ($itemCount > 2) {
                    $plan[2] = $second;
                }
                if ($itemCount > 3) {
                    $plan[3] = $first;
                }
                break;
            default: // almost done: everything but the last piece
                for ($p = 1; $p < $itemCount; $p++) {
                    $plan[$p] = $first;
                }
        }

        return $plan;
    }

    /**
     * Where an unloaded piece physically is. Most orders are binned in the
     * Sydney bay; a few are part-binned, parked in holding, or not yet scanned.
     */
    private function dockState(int $index, int $piece): string
    {
        return match ($index % 9) {
            0, 1, 2, 3, 4 => 'in_bay',
            5, 6 => $piece % 2 === 1 ? 'in_bay' : 'received',
            7 => 'holding',
            default => 'pending',
        };
    }

    private function pallet(
        Consignment $consignment,
        string $connote,
        int $piece,
        ?Manifest $manifest,
        User $loader,
        string $sender,
        int $index,
    ): void {
        $barcode = sprintf('%s-%02d', $connote, $piece);
        $dock = $manifest ? 'in_bay' : $this->dockState($index, $piece);
        $dg = $this->dangerousGoods($sender, $index, $piece);

        $pallet = HandlingUnit::query()->updateOrCreate(
            ['barcode' => $barcode],
            [
                'consignment_id' => $consignment->getKey(),
                'piece_number' => $piece,
                'weight_kg' => mt_rand(40, 900),
                'dg_class' => $dg[0] ?? null,
                'un_number' => $dg[1] ?? null,
                'proper_shipping_name' => $dg[2] ?? null,
                'is_food' => in_array($sender, self::FOOD_SENDERS, true),
                'current_status' => match (true) {
                    $manifest !== null => HandlingUnitStatus::Loaded,
                    $dock === 'in_bay', $dock === 'holding' => HandlingUnitStatus::Staged,
                    $dock === 'received' => HandlingUnitStatus::Received,
                    default => HandlingUnitStatus::Pending,
                },
                'current_location_id' => match (true) {
                    $manifest !== null, $dock === 'received', $dock === 'pending' => null,
                    $dock === 'holding' => $this->holding->getKey(),
                    default => $this->bay->getKey(),
                },
            ],
        );

        // Movement history: scanned in at the dock, then binned (unless it
        // never made it past the dock or hasn't arrived).
        if ($dock !== 'pending') {
            $receivedAt = now()->subMinutes(mt_rand(300, 600));
            $this->event($pallet, $loader, EventType::Received, $receivedAt, "rcv-{$barcode}", ['location_code' => 'MEL05']);

            if ($dock !== 'received') {
                $location = $dock === 'holding' ? $this->holding : $this->bay;
                $this->event($pallet, $loader, EventType::Staged, $receivedAt->addMinutes(mt_rand(3, 20)), "stg-{$barcode}", ['location_code' => $location->code]);
            }
        }

        if (! $manifest) {
            return;
        }

        $clientEventId = $this->clientEventId("syd-{$barcode}");
        $loadedAt = now()->subMinutes(mt_rand(5, 240));

        ManifestItem::query()->updateOrCreate(
            ['handling_unit_id' => $pallet->getKey()],
            [
                'manifest_id' => $manifest->getKey(),
                'loaded_by' => $loader->getKey(),
                'client_event_id' => $clientEventId,
                'loaded_at' => $loadedAt,
            ] + $this->position($manifest, $dg !== null),
        );

        $this->event($pallet, $loader, EventType::Loaded, $loadedAt, "syd-{$barcode}", [
            'manifest_id' => $manifest->getKey(),
            'manifest_number' => $manifest->manifest_number,
        ]);
    }

    /**
     * Most DG pallets have already been placed on the trailer by the loader.
     *
     * @return array{trailer_unit: int|null, trailer_row: int|null, trailer_side: string|null}
     */
    private function position(Manifest $manifest, bool $isDg): array
    {
        $none = ['trailer_unit' => null, 'trailer_row' => null, 'trailer_side' => null];

        if (! $isDg || mt_rand(1, 10) > 7) {
            return $none;
        }

        $rows = $manifest->trailer_type->rows();

        for ($try = 0; $try < 40; $try++) {
            $unit = mt_rand(1, count($rows));
            $row = mt_rand(1, $rows[$unit - 1]);
            $side = mt_rand(0, 1) === 0 ? 'D' : 'P';
            $key = "{$unit}-{$row}-{$side}";

            if (! isset($this->slots[$manifest->getKey()][$key])) {
                $this->slots[$manifest->getKey()][$key] = true;

                return ['trailer_unit' => $unit, 'trailer_row' => $row, 'trailer_side' => $side];
            }
        }

        return $none;
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private function dangerousGoods(string $sender, int $index, int $piece): ?array
    {
        $products = self::DG_BY_SENDER[$sender] ?? null;

        if (! $products) {
            return null;
        }

        // Chemicals are all DG; pool/garden and auto parts mix DG with ordinary stock.
        $isDg = $sender === 'Redfern Industrial Chemicals' || $piece % 3 === 1;

        return $isDg ? $products[($index + $piece) % count($products)] : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function event(
        HandlingUnit $pallet,
        User $actor,
        EventType $type,
        \DateTimeInterface $at,
        string $key,
        array $metadata,
    ): void {
        $clientEventId = $this->clientEventId($key);

        OperationalEvent::query()->updateOrCreate(
            ['client_event_id' => $clientEventId],
            [
                'handling_unit_id' => $pallet->getKey(),
                'actor_id' => $actor->getKey(),
                'event_type' => $type,
                'occurred_at' => $at,
                'received_at' => $at,
                'metadata' => $metadata + ['seeded' => true],
            ],
        );
    }

    private function clientEventId(string $key): string
    {
        return sprintf('00000000-0000-4000-8000-%012s', dechex(crc32($key)));
    }
}
