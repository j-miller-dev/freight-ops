<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A busy Sydney-bound day: many manifests (enough to page), and consignments in
 * every load state so split and "what's left" views have real data. All names
 * are fictional.
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

    public function run(): void
    {
        $loader = User::query()->where('email', 'test@example.com')->firstOrFail();
        $sydney = Depot::query()->where('code', 'SYD')->firstOrFail();
        $melbourne = Depot::query()->where('code', 'MEL')->firstOrFail();

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
        $manifests = [];

        for ($n = 1; $n <= self::MANIFEST_COUNT; $n++) {
            $manifest = Manifest::query()->updateOrCreate(
                ['source' => 'seed', 'external_id' => "seed-mel-syd-{$n}"],
                [
                    'depot_id' => $origin->getKey(),
                    'manifest_number' => sprintf('MEL-SYD-2610%02d', $n),
                    'service_date' => today()->toDateString(),
                    // The last one is already gone, to exercise the closed state.
                    'status' => $n === self::MANIFEST_COUNT ? 'closed' : 'open',
                    'trailer_label' => "SYD FreightOps {$n}",
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

        $consignment = Consignment::query()->updateOrCreate(
            ['connote_number' => $connote],
            [
                'destination_depot_id' => $sydney->getKey(),
                'item_count' => $itemCount,
                'sender_name' => self::SENDERS[$index % count(self::SENDERS)],
                'receiver_name' => self::RECEIVERS[$index % count(self::RECEIVERS)],
                'service_code' => self::SERVICES[$index % count(self::SERVICES)],
            ],
        );

        // Open manifests only: loaders never build onto a closed one.
        $open = array_values(array_filter($manifests, fn (Manifest $m) => $m->status === 'open'));
        $plan = $this->loadPlan($index, $itemCount, count($open));

        for ($piece = 1; $piece <= $itemCount; $piece++) {
            $manifest = isset($plan[$piece]) ? $open[$plan[$piece]] : null;

            $this->pallet($consignment, $connote, $piece, $manifest, $loader);
        }
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

    private function pallet(
        Consignment $consignment,
        string $connote,
        int $piece,
        ?Manifest $manifest,
        User $loader,
    ): void {
        $barcode = sprintf('%s-%02d', $connote, $piece);

        $pallet = HandlingUnit::query()->updateOrCreate(
            ['barcode' => $barcode],
            [
                'consignment_id' => $consignment->getKey(),
                'piece_number' => $piece,
                'weight_kg' => mt_rand(40, 900),
                'current_status' => $manifest ? HandlingUnitStatus::Loaded : HandlingUnitStatus::Pending,
            ],
        );

        if (! $manifest) {
            return;
        }

        $clientEventId = sprintf('00000000-0000-4000-8000-%012s', dechex(crc32("syd-{$barcode}")));
        $loadedAt = now()->subMinutes(mt_rand(5, 240));

        ManifestItem::query()->updateOrCreate(
            ['handling_unit_id' => $pallet->getKey()],
            [
                'manifest_id' => $manifest->getKey(),
                'loaded_by' => $loader->getKey(),
                'client_event_id' => $clientEventId,
                'loaded_at' => $loadedAt,
            ],
        );

        OperationalEvent::query()->updateOrCreate(
            ['client_event_id' => $clientEventId],
            [
                'handling_unit_id' => $pallet->getKey(),
                'actor_id' => $loader->getKey(),
                'event_type' => EventType::Loaded,
                'occurred_at' => $loadedAt,
                'received_at' => $loadedAt,
                'metadata' => [
                    'manifest_id' => $manifest->getKey(),
                    'manifest_number' => $manifest->manifest_number,
                    'seeded' => true,
                ],
            ],
        );
    }
}
