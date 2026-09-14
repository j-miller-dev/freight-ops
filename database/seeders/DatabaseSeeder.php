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
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Seed a repeatable warehouse loading scenario. */
    public function run(): void
    {
        $loader = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User'],
        );

        $melbourne = $this->depot('MEL', 'Melbourne Cross-Dock', 'Australia/Melbourne');
        $sydney = $this->depot('SYD', 'Sydney Depot', 'Australia/Sydney');
        $brisbane = $this->depot('BNE', 'Brisbane Depot', 'Australia/Brisbane');

        $today = today();
        $yesterday = today()->subDay();
        $todaySydney = $this->manifest($melbourne, $sydney, 'fixture-mel-syd-today', 'MEL-SYD-260913', $today, 'open');
        $todayBrisbane = $this->manifest($melbourne, $brisbane, 'fixture-mel-bne-today', 'MEL-BNE-260913', $today, 'closed');
        $yesterdaySydney = $this->manifest($melbourne, $sydney, 'fixture-mel-syd-yesterday', 'MEL-SYD-260912', $yesterday, 'closed');

        $split = $this->consignment('CN-SPLIT-001', $sydney, 3);
        $this->pallet($split, 'PALLET-SPLIT-001', 1, $todaySydney, $loader, 'split-001');
        $this->pallet($split, 'PALLET-SPLIT-002', 2, $todaySydney, $loader, 'split-002');
        $this->pallet($split, 'PALLET-SPLIT-003', 3, $yesterdaySydney, $loader, 'split-003');

        $multi = $this->consignment('CN-MULTI-001', $sydney, 4);
        $this->pallet($multi, 'PALLET-MULTI-001', 1, $todaySydney, $loader, 'multi-001');
        $this->pallet($multi, 'PALLET-MULTI-002', 2, $todaySydney, $loader, 'multi-002');
        $this->pallet($multi, 'PALLET-MULTI-003', 3);
        $this->pallet($multi, 'PALLET-MULTI-004', 4);

        $wrongDestination = $this->consignment('CN-WRONG-001', $sydney, 1);
        $this->pallet($wrongDestination, 'PALLET-WRONG-001', 1, $todayBrisbane, $loader, 'wrong-destination-001');

        $alreadyLoaded = $this->consignment('CN-LOADED-001', $sydney, 1);
        $this->pallet($alreadyLoaded, 'PALLET-LOADED-001', 1, $todaySydney, $loader, 'loaded-001');
    }

    private function depot(string $code, string $name, string $timezone): Depot
    {
        return Depot::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'timezone' => $timezone, 'is_active' => true],
        );
    }

    private function manifest(
        Depot $sourceDepot,
        Depot $destination,
        string $externalId,
        string $number,
        mixed $serviceDate,
        string $status,
    ): Manifest {
        $manifest = Manifest::query()->updateOrCreate(
            ['source' => 'fixture', 'external_id' => $externalId],
            [
                'depot_id' => $sourceDepot->getKey(),
                'manifest_number' => $number,
                'service_date' => $serviceDate,
                'status' => $status,
                'closed_at' => $status === 'closed' ? now() : null,
                'trailer_label' => $number,
                'source_updated_at' => now(),
                'last_synced_at' => now(),
            ],
        );

        $manifest->destinations()->syncWithoutDetaching([
            $destination->getKey() => ['is_primary' => true],
        ]);

        return $manifest;
    }

    private function consignment(string $connote, Depot $destination, int $itemCount): Consignment
    {
        return Consignment::query()->updateOrCreate(
            ['connote_number' => $connote],
            ['destination_depot_id' => $destination->getKey(), 'item_count' => $itemCount],
        );
    }

    private function pallet(
        Consignment $consignment,
        string $barcode,
        int $pieceNumber,
        ?Manifest $manifest = null,
        ?User $loader = null,
        ?string $eventKey = null,
    ): HandlingUnit {
        $pallet = HandlingUnit::query()->updateOrCreate(
            ['barcode' => $barcode],
            [
                'consignment_id' => $consignment->getKey(),
                'piece_number' => $pieceNumber,
                'current_status' => $manifest ? HandlingUnitStatus::Loaded : HandlingUnitStatus::Pending,
            ],
        );

        if (! $manifest || ! $loader) {
            return $pallet;
        }

        $clientEventId = sprintf(
            '00000000-0000-4000-8000-%012s',
            dechex(crc32($eventKey)),
        );
        $assignment = ManifestItem::query()->updateOrCreate(
            ['handling_unit_id' => $pallet->getKey()],
            [
                'manifest_id' => $manifest->getKey(),
                'loaded_by' => $loader->getKey(),
                'client_event_id' => $clientEventId,
                'loaded_at' => now(),
            ],
        );

        OperationalEvent::query()->updateOrCreate(
            ['client_event_id' => $clientEventId],
            [
                'handling_unit_id' => $pallet->getKey(),
                'actor_id' => $loader->getKey(),
                'event_type' => EventType::Loaded,
                'occurred_at' => $assignment->loaded_at,
                'received_at' => now(),
                'metadata' => ['manifest_id' => $manifest->getKey(), 'manifest_number' => $manifest->manifest_number, 'seeded' => true],
            ],
        );

        return $pallet;
    }
}
