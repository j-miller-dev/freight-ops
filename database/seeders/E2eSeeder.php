<?php

namespace Database\Seeders;

use App\Enums\HandlingUnitStatus;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use Illuminate\Database\Seeder;

/**
 * Clean freight for the Playwright suite, on top of the normal dev seed: single-
 * piece Sydney consignments from a sender with no holding rule, not dangerous
 * goods and not food, so a test can scan one without warnings or hold notices.
 * Barcodes are E2E-0001 and up. The sender is fictional.
 */
class E2eSeeder extends Seeder
{
    public const SENDER = 'E2E Freight Co';

    public const PALLETS = 12;

    public function run(): void
    {
        $sydney = Depot::query()->where('code', 'SYD')->firstOrFail();

        for ($n = 1; $n <= self::PALLETS; $n++) {
            $consignment = Consignment::factory()->create([
                'connote_number' => sprintf('E2E%07d', $n),
                'destination_depot_id' => $sydney->getKey(),
                'sender_name' => self::SENDER,
                'service_code' => 'STANDARD',
                'item_count' => 1,
            ]);

            HandlingUnit::factory()->for($consignment)->create([
                'barcode' => sprintf('E2E-%04d', $n),
                'piece_number' => 1,
                'current_status' => HandlingUnitStatus::Pending,
            ]);
        }
    }
}
