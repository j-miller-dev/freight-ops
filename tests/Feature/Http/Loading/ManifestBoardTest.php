<?php

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Location;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function boardManifest(Depot $destination): Manifest
{
    $manifest = Manifest::factory()->create(['status' => 'open']);
    $manifest->destinations()->attach($destination, ['is_primary' => true]);

    return $manifest;
}

function boardBay(Depot $destination): Location
{
    return Location::factory()->create([
        'code' => 'SYD02',
        'type' => LocationType::Bay,
        'destination_depot_id' => $destination->getKey(),
    ]);
}

/** @param  array<string, mixed>  $attributes */
function boardPallet(Consignment $consignment, int $piece, array $attributes = []): HandlingUnit
{
    return HandlingUnit::factory()->for($consignment)->create(
        $attributes + ['piece_number' => $piece, 'current_status' => HandlingUnitStatus::Pending],
    );
}

function boardLoad(HandlingUnit $pallet, Manifest $manifest): void
{
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $pallet->getKey(),
    ]);
    $pallet->forceFill(['current_status' => HandlingUnitStatus::Loaded])->save();
}

it('summarises DG classes, food and clashes on a trailer', function () {
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey(), 'item_count' => 3]);

    boardLoad(boardPallet($consignment, 1, ['dg_class' => '8']), $manifest);
    boardLoad(boardPallet($consignment, 2, ['dg_class' => '3']), $manifest);
    boardLoad(boardPallet($consignment, 3, ['is_food' => true]), $manifest);
    boardPallet(Consignment::factory()->create(['destination_depot_id' => $destination->getKey()]), 1, ['dg_class' => '6.1']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.manifest.summary', $manifest))
        ->assertOk()
        ->assertJsonPath('data.loaded_count', 3)
        ->assertJsonPath('data.food_count', 1)
        ->assertJsonPath('data.dg', [['class' => '3', 'count' => 1], ['class' => '8', 'count' => 1]])
        ->assertJsonPath('data.food_conflicts', ['8']);
});

it('classifies each consignment by where its remaining pieces are', function () {
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    $other = boardManifest($destination);
    $bay = boardBay($destination);
    $make = fn (int $count) => Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => $count,
    ]);

    $complete = $make(1);
    boardLoad(boardPallet($complete, 1), $manifest);

    $ready = $make(3);
    boardLoad(boardPallet($ready, 1), $manifest);
    boardPallet($ready, 2, ['current_status' => HandlingUnitStatus::Staged, 'current_location_id' => $bay->getKey()]);
    boardLoad(boardPallet($ready, 3), $other);

    $notReady = $make(3);
    boardLoad(boardPallet($notReady, 1), $manifest);
    boardPallet($notReady, 2, ['current_status' => HandlingUnitStatus::Staged, 'current_location_id' => $bay->getKey()]);
    boardPallet($notReady, 3, ['current_status' => HandlingUnitStatus::Received]);

    $unscanned = $make(2);
    boardLoad(boardPallet($unscanned, 1), $manifest);
    // Piece 2 has no handling unit at all: it has not been scanned in.

    $this->actingAs(User::factory()->create());

    $states = collect($this->getJson(route('loading.manifest.consignments', $manifest))->assertOk()->json('data'))
        ->pluck('state', 'connote_number');

    expect($states[$complete->connote_number])->toBe('complete')
        ->and($states[$ready->connote_number])->toBe('ready')
        ->and($states[$notReady->connote_number])->toBe('not_ready')
        ->and($states[$unscanned->connote_number])->toBe('not_ready');

    $row = collect($this->getJson(route('loading.manifest.consignments', $manifest))->json('data'))
        ->firstWhere('connote_number', $ready->connote_number);

    expect($row)->toMatchArray(['on_trailer' => 1, 'on_other_trailers' => 1, 'in_bay' => 1, 'not_in_bay' => 0, 'bay_code' => 'SYD02']);

    $this->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'ready']))
        ->assertJsonPath('meta.total', 1);
    $this->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'not_ready']))
        ->assertJsonPath('meta.total', 2);
    $this->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'complete']))
        ->assertJsonPath('meta.total', 1);
});

it('rejects an unknown consignment filter', function () {
    $manifest = boardManifest(Depot::factory()->create());

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'nonsense']))
        ->assertUnprocessable();
});

it('traces each piece of a consignment', function () {
    $user = User::factory()->create();
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    $bay = boardBay($destination);
    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey(), 'item_count' => 3]);

    $loaded = boardPallet($consignment, 1);
    boardLoad($loaded, $manifest);
    OperationalEvent::factory()->create([
        'handling_unit_id' => $loaded->getKey(),
        'actor_id' => $user->getKey(),
        'event_type' => EventType::Staged,
        'metadata' => ['location_code' => 'SYD02'],
    ]);
    boardPallet($consignment, 2, ['current_status' => HandlingUnitStatus::Staged, 'current_location_id' => $bay->getKey()]);
    boardPallet($consignment, 3);

    $pieces = collect($this->actingAs($user)
        ->getJson(route('loading.manifest.consignment', [$manifest, $consignment]))
        ->assertOk()
        ->json('data.pieces'));

    expect($pieces->pluck('where.state')->all())->toBe(['this_trailer', 'in_bay', 'not_scanned_in'])
        ->and($pieces[0]['events'][0])->toMatchArray(['type' => 'staged', 'location_code' => 'SYD02', 'actor' => $user->name]);
});

it('stores the trailer type and rejects unknown ones', function () {
    $manifest = boardManifest(Depot::factory()->create());

    $this->actingAs(User::factory()->create());

    $this->patchJson(route('loading.manifest.trailer', $manifest), ['trailer_type' => 'b_triple'])
        ->assertOk()
        ->assertJsonPath('data.trailer_type', 'b_triple');

    expect($manifest->fresh()->trailer_type->value)->toBe('b_triple');

    $this->patchJson(route('loading.manifest.trailer', $manifest), ['trailer_type' => 'skateboard'])
        ->assertUnprocessable();
});

function boardHoldingLane(string $code, int $lane): Location
{
    return Location::factory()->create(['code' => $code, 'type' => LocationType::HoldingArea, 'lane' => $lane]);
}

it('flags a consignment split across manifests and names the other manifests', function () {
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    $otherA = boardManifest($destination);
    $otherB = boardManifest($destination);
    boardBay($destination);
    $make = fn (int $count) => Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => $count,
    ]);

    $split = $make(3);
    boardLoad(boardPallet($split, 1), $manifest);
    boardLoad(boardPallet($split, 2), $otherA);
    boardLoad(boardPallet($split, 3), $otherB);

    $whole = $make(2);
    boardLoad(boardPallet($whole, 1), $manifest);
    boardLoad(boardPallet($whole, 2), $manifest);

    $this->actingAs(User::factory()->create());

    $rows = collect($this->getJson(route('loading.manifest.consignments', $manifest))->assertOk()->json('data'))
        ->keyBy('connote_number');

    expect($rows[$split->connote_number])->toMatchArray([
        'split' => true,
        'split_manifests' => collect([$otherA->manifest_number, $otherB->manifest_number])->sort()->values()->all(),
    ])->and($rows[$whole->connote_number])->toMatchArray(['split' => false, 'split_manifests' => []]);

    $filtered = $this->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'split']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    expect($filtered->json('data.0.connote_number'))->toBe($split->connote_number);
});

it('counts pieces in a holding lane as held, not missing, and keeps them out of ready', function () {
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    $bay = boardBay($destination);
    $lane = boardHoldingLane('HOLD2', 2);

    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey(), 'item_count' => 3]);
    boardLoad(boardPallet($consignment, 1), $manifest);
    boardPallet($consignment, 2, ['current_status' => HandlingUnitStatus::Staged, 'current_location_id' => $bay->getKey()]);
    boardPallet($consignment, 3, ['current_status' => HandlingUnitStatus::Held, 'current_location_id' => $lane->getKey()]);

    $this->actingAs(User::factory()->create());

    $row = $this->getJson(route('loading.manifest.consignments', $manifest))->assertOk()->json('data.0');

    expect($row)->toMatchArray([
        'state' => 'held',
        'in_bay' => 1,
        'held' => 1,
        'held_lanes' => ['HOLD2'],
        'not_in_bay' => 0,
    ]);

    $this->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'ready']))->assertJsonPath('meta.total', 0);
    $this->getJson(route('loading.manifest.consignments', [$manifest, 'filter' => 'not_ready']))->assertJsonPath('meta.total', 0);
});

it('still reports not ready when a piece is missing even if others are held', function () {
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    boardBay($destination);
    $lane = boardHoldingLane('HOLD1', 1);

    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey(), 'item_count' => 3]);
    boardLoad(boardPallet($consignment, 1), $manifest);
    boardPallet($consignment, 2, ['current_status' => HandlingUnitStatus::Held, 'current_location_id' => $lane->getKey()]);
    // Piece 3 has no handling unit: it has not arrived.

    $this->actingAs(User::factory()->create());

    $row = $this->getJson(route('loading.manifest.consignments', $manifest))->json('data.0');

    expect($row)->toMatchArray(['state' => 'not_ready', 'held' => 1, 'not_in_bay' => 1]);
});

it('labels each piece by whether it has arrived and where it is', function () {
    $destination = Depot::factory()->create();
    $manifest = boardManifest($destination);
    $other = boardManifest($destination);
    $bay = boardBay($destination);
    $lane = boardHoldingLane('HOLD3', 3);
    $dock = Location::factory()->create(['code' => 'DOCK1', 'type' => LocationType::Dock]);
    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey(), 'item_count' => 7]);

    boardLoad(boardPallet($consignment, 1), $manifest);
    boardLoad(boardPallet($consignment, 2), $other);
    boardPallet($consignment, 3, ['current_status' => HandlingUnitStatus::Staged, 'current_location_id' => $bay->getKey()]);
    boardPallet($consignment, 4, ['current_status' => HandlingUnitStatus::Held, 'current_location_id' => $lane->getKey()]);
    boardPallet($consignment, 5, ['current_status' => HandlingUnitStatus::Received, 'current_location_id' => $dock->getKey()]);
    boardPallet($consignment, 6, ['current_status' => HandlingUnitStatus::Received]);
    boardPallet($consignment, 7);

    $pieces = collect($this->actingAs(User::factory()->create())
        ->getJson(route('loading.manifest.consignment', [$manifest, $consignment]))
        ->assertOk()
        ->json('data.pieces'));

    expect($pieces->pluck('where.state')->all())->toBe([
        'this_trailer', 'other_trailer', 'in_bay', 'held', 'elsewhere', 'elsewhere', 'not_scanned_in',
    ])->and($pieces->pluck('where.label')->all())->toBe([
        'On this trailer',
        'On '.$other->manifest_number,
        'In the SYD02 bay',
        'Held in HOLD3',
        'Arrived, in DOCK1',
        'Arrived, not in bay',
        'Not arrived yet',
    ]);
});
