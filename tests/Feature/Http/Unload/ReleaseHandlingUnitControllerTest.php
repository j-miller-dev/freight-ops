<?php

use App\Actions\Unload\MoveHandlingUnit;
use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\HoldingRule;
use App\Models\Location;
use App\Models\User;
use App\Support\Unload\BayDirection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function heldPallet(): HandlingUnit
{
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);
    $destination = Depot::factory()->create(['code' => 'SYD']);
    $lane = Location::factory()->create([
        'depot_id' => $home->getKey(),
        'code' => 'HOLD1',
        'type' => LocationType::HoldingArea,
        'lane' => 1,
    ]);
    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey()]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();

    app(MoveHandlingUnit::class)->handle(
        handlingUnit: $pallet,
        direction: new BayDirection($lane, 'Held for test', isHolding: true, maxHoldDays: 3, lane: 1),
        runner: User::factory()->create(),
        clientEventId: (string) Str::uuid(),
        occurredAt: now(),
    );

    return $pallet->refresh();
}

it('lets a scaler release a held pallet', function () {
    $pallet = heldPallet();

    $this->actingAs(User::factory()->scaler()->create())
        ->postJson(route('unload.pallets.release', $pallet))
        ->assertOk()
        ->assertJsonPath('data.id', $pallet->getKey());

    expect($pallet->refresh()->released_from_hold_at)->not->toBeNull();
});

it('forbids an operator from releasing a held pallet', function () {
    $pallet = heldPallet();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.pallets.release', $pallet))
        ->assertForbidden();

    expect($pallet->refresh()->released_from_hold_at)->toBeNull();
});

it('rejects a guest', function () {
    $this->postJson(route('unload.pallets.release', heldPallet()))
        ->assertUnauthorized();
});

it('refuses to release a pallet that is not held, with a 409', function () {
    $pallet = HandlingUnit::factory()->create();

    $this->actingAs(User::factory()->scaler()->create())
        ->postJson(route('unload.pallets.release', $pallet))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'handling_unit_not_held');
});

it('sends a released pallet to its real bay instead of back into holding', function () {
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);
    $destination = Depot::factory()->create(['code' => 'SYD']);
    Location::factory()->create([
        'depot_id' => $home->getKey(),
        'code' => 'SYD02',
        'type' => LocationType::Bay,
        'destination_depot_id' => $destination->getKey(),
    ]);
    $lane = Location::factory()->create([
        'depot_id' => $home->getKey(),
        'code' => 'HOLD1',
        'type' => LocationType::HoldingArea,
        'lane' => 1,
    ]);
    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'sender_name' => 'Harbourline Hardware',
    ]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'base_lane' => 1]);

    app(MoveHandlingUnit::class)->handle(
        handlingUnit: $pallet,
        direction: new BayDirection($lane, 'Held', isHolding: true, maxHoldDays: 3, lane: 1),
        runner: User::factory()->create(),
        clientEventId: (string) Str::uuid(),
        occurredAt: now(),
    );

    $this->actingAs(User::factory()->scaler()->create())
        ->postJson(route('unload.pallets.release', $pallet))
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertOk()
        ->assertJsonPath('data.bay.code', 'SYD02')
        ->assertJsonPath('data.bay.is_holding', false);
});
