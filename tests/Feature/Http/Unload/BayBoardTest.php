<?php

use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Location;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @return array{destination: Depot, bay: Location, lane: Location} */
function bayBoardYard(): array
{
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);
    $destination = Depot::factory()->create(['code' => 'SYD']);

    return [
        'destination' => $destination,
        'bay' => Location::factory()->create([
            'depot_id' => $home->getKey(),
            'code' => 'SYD02',
            'type' => LocationType::Bay,
            'destination_depot_id' => $destination->getKey(),
        ]),
        'lane' => Location::factory()->create([
            'depot_id' => $home->getKey(),
            'code' => 'HOLD1',
            'type' => LocationType::HoldingArea,
        ]),
    ];
}

/** A consignment of $items pallets with the given number sitting in a location. */
function consignmentWith(Depot $destination, int $items, int $placed, ?Location $at, string $connote): Consignment
{
    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => $items,
        'connote_number' => $connote,
    ]);

    foreach (range(1, $items) as $piece) {
        HandlingUnit::factory()->for($consignment)->create([
            'piece_number' => $piece,
            'current_location_id' => $piece <= $placed ? $at?->getKey() : null,
        ]);
    }

    return $consignment;
}

it('shows how much of the scanned consignment is in its bay', function () {
    $yard = bayBoardYard();
    $consignment = consignmentWith($yard['destination'], 7, 3, $yard['bay'], 'SY000001');

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $consignment))
        ->assertOk()
        ->assertJsonPath('data.bay.code', 'SYD02')
        ->assertJsonPath('data.current.connote_number', 'SY000001')
        ->assertJsonPath('data.current.item_count', 7)
        ->assertJsonPath('data.current.in_bay', 3)
        ->assertJsonPath('data.current.out_there', 4);
});

it('lists the other consignments in the bay, closest to done first', function () {
    $yard = bayBoardYard();
    $current = consignmentWith($yard['destination'], 7, 1, $yard['bay'], 'SY000001');
    consignmentWith($yard['destination'], 5, 1, $yard['bay'], 'SY000002'); // 1 of 5
    consignmentWith($yard['destination'], 4, 3, $yard['bay'], 'SY000003'); // 3 of 4: nearly there
    consignmentWith($yard['destination'], 2, 2, $yard['bay'], 'SY000004'); // complete

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $current))
        ->assertOk();

    expect(collect($response->json('data.others'))->pluck('connote_number')->all())
        ->toBe(['SY000003', 'SY000002'])
        ->and($response->json('data.complete_count'))->toBe(1)
        ->and($response->json('data.in_progress_count'))->toBe(2);
});

it('counts pallets in a holding lane apart from the bay', function () {
    $yard = bayBoardYard();
    $consignment = consignmentWith($yard['destination'], 5, 2, $yard['bay'], 'SY000001');

    HandlingUnit::query()
        ->where('consignment_id', $consignment->getKey())
        ->whereNull('current_location_id')
        ->limit(1)
        ->update(['current_location_id' => $yard['lane']->getKey()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $consignment))
        ->assertOk()
        ->assertJsonPath('data.current.in_bay', 2)
        ->assertJsonPath('data.current.held', 1)
        ->assertJsonPath('data.current.out_there', 2);
});

it('does not count pieces already loaded on a trailer as still out there', function () {
    $yard = bayBoardYard();
    $consignment = consignmentWith($yard['destination'], 4, 2, $yard['bay'], 'SY000001');

    $loaded = HandlingUnit::query()
        ->where('consignment_id', $consignment->getKey())
        ->whereNull('current_location_id')
        ->first();
    ManifestItem::factory()->create(['handling_unit_id' => $loaded->getKey()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $consignment))
        ->assertOk()
        ->assertJsonPath('data.current.in_bay', 2)
        ->assertJsonPath('data.current.loaded', 1)
        ->assertJsonPath('data.current.out_there', 1);
});

it('ignores a pallet loaded on a trailer when counting what is in the bay', function () {
    $yard = bayBoardYard();
    $consignment = consignmentWith($yard['destination'], 3, 3, $yard['bay'], 'SY000001');

    ManifestItem::factory()->create([
        'handling_unit_id' => HandlingUnit::query()->where('consignment_id', $consignment->getKey())->value('id'),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $consignment))
        ->assertOk()
        ->assertJsonPath('data.current.in_bay', 2);
});

it('leaves out freight for another destination that was left in the bay', function () {
    $yard = bayBoardYard();
    $current = consignmentWith($yard['destination'], 3, 1, $yard['bay'], 'SY000001');
    $elsewhere = Depot::factory()->create(['code' => 'BNE']);
    consignmentWith($elsewhere, 4, 3, $yard['bay'], 'BN000001'); // wrongly placed

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $current))
        ->assertOk()
        ->assertJsonCount(0, 'data.others');
});

it('still answers for a consignment with nothing placed yet', function () {
    $yard = bayBoardYard();
    $consignment = consignmentWith($yard['destination'], 6, 0, null, 'SY000001');

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.bay-board', $consignment))
        ->assertOk()
        ->assertJsonPath('data.current.in_bay', 0)
        ->assertJsonPath('data.current.out_there', 6);
});

it('requires a signed-in user', function () {
    $yard = bayBoardYard();
    $consignment = consignmentWith($yard['destination'], 2, 0, null, 'SY000001');

    $this->getJson(route('unload.bay-board', $consignment))->assertUnauthorized();
});
