<?php

use App\Actions\Unload\MoveHandlingUnit;
use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\HoldingRule;
use App\Models\Location;
use App\Models\Manifest;
use App\Models\User;
use App\Support\Unload\BayDirection;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @return array{home: Depot, destination: Depot, lane: Location} */
function holdingLaneYard(int $laneNumber = 1): array
{
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);

    return [
        'home' => $home,
        'destination' => Depot::factory()->create(['code' => 'SYD']),
        'lane' => Location::factory()->create([
            'depot_id' => $home->getKey(),
            'code' => "HOLD{$laneNumber}",
            'type' => LocationType::HoldingArea,
            'lane' => $laneNumber,
        ]),
    ];
}

/** Places a pallet straight into a holding lane, bypassing BayDirector's rule matching for precise control over timing. */
function holdPalletInLane(Depot $destination, Location $lane, ?string $senderName = null, ?CarbonInterface $heldSince = null): HandlingUnit
{
    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'sender_name' => $senderName,
        'item_count' => 1,
    ]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();

    app(MoveHandlingUnit::class)->handle(
        handlingUnit: $pallet,
        direction: new BayDirection(
            location: $lane,
            reason: 'Held for test',
            isHolding: true,
            maxHoldDays: 3,
            lane: $lane->lane,
        ),
        runner: User::factory()->create(),
        clientEventId: (string) Str::uuid(),
        occurredAt: $heldSince ?? now(),
    );

    return $pallet->refresh();
}

it('rejects a guest', function () {
    $this->getJson(route('unload.holding-lanes'))->assertUnauthorized();
});

it('omits lanes with nothing held in them', function () {
    holdingLaneYard();

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.holding-lanes'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('reports a lane as settled well within its hold limit', function () {
    $yard = holdingLaneYard();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'max_hold_days' => 3]);
    holdPalletInLane($yard['destination'], $yard['lane'], 'Harbourline Hardware');

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.holding-lanes'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'HOLD1')
        ->assertJsonPath('data.0.pallet_count', 1)
        ->assertJsonPath('data.0.status', 'settled')
        ->assertJsonPath('data.0.overdue_count', 0);
});

it('flags a lane overdue once a pallet has sat past the hold limit', function () {
    $yard = holdingLaneYard();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'max_hold_days' => 3]);
    holdPalletInLane($yard['destination'], $yard['lane'], 'Harbourline Hardware', now()->subDays(4));

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.holding-lanes'))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'overdue')
        ->assertJsonPath('data.0.overdue_count', 1)
        ->assertJsonPath('data.0.reasons.0', 'Held 4d, past the 3-day limit');
});

it('flags a lane as an opportunity when a trailer to its destination leaves soon', function () {
    $yard = holdingLaneYard();
    $manifest = Manifest::factory()->create(['status' => 'open', 'departs_at' => now()->addMinutes(20)]);
    $manifest->destinations()->attach($yard['destination'], ['is_primary' => true]);
    holdPalletInLane($yard['destination'], $yard['lane']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.holding-lanes'))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'opportunity')
        ->assertJsonPath('data.0.reasons.0', "Trailer to {$manifest->manifest_number} leaves in 20m");
});

it('keeps lanes separate and counts each one on its own', function () {
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);
    $destination = Depot::factory()->create(['code' => 'SYD']);
    $lane1 = Location::factory()->create(['depot_id' => $home->getKey(), 'code' => 'HOLD1', 'type' => LocationType::HoldingArea, 'lane' => 1]);
    $lane2 = Location::factory()->create(['depot_id' => $home->getKey(), 'code' => 'HOLD2', 'type' => LocationType::HoldingArea, 'lane' => 2]);

    holdPalletInLane($destination, $lane1);
    holdPalletInLane($destination, $lane2);
    holdPalletInLane($destination, $lane2);

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('unload.holding-lanes'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $byCode = collect($response->json('data'))->keyBy('code');
    expect($byCode['HOLD1']['pallet_count'])->toBe(1)
        ->and($byCode['HOLD2']['pallet_count'])->toBe(2);
});
