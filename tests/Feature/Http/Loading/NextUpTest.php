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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{manifest: Manifest, destination: Depot, bay: Location}
 */
function nextUpYard(?int $leavesInMinutes = 600): array
{
    $destination = Depot::factory()->create();
    $manifest = Manifest::factory()->create([
        'status' => 'open',
        'departs_at' => $leavesInMinutes === null ? null : now()->addMinutes($leavesInMinutes),
    ]);
    $manifest->destinations()->attach($destination, ['is_primary' => true]);

    return [
        'manifest' => $manifest,
        'destination' => $destination,
        'bay' => Location::factory()->create([
            'code' => 'SYD02',
            'type' => LocationType::Bay,
            'destination_depot_id' => $destination->getKey(),
        ]),
    ];
}

/** @param  array<string, mixed>  $attributes */
function nextUpConsignment(Depot $destination, array $attributes = []): Consignment
{
    return Consignment::factory()->create($attributes + [
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
        'service_code' => 'STANDARD',
    ]);
}

/** A piece sitting in a location (or nowhere). */
function nextUpPiece(Consignment $consignment, int $piece, ?Location $at, array $attributes = []): HandlingUnit
{
    return HandlingUnit::factory()->for($consignment)->create($attributes + [
        'piece_number' => $piece,
        'current_status' => HandlingUnitStatus::Staged,
        'current_location_id' => $at?->getKey(),
    ]);
}

function nextUpLoad(HandlingUnit $piece, Manifest $manifest): void
{
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $piece->getKey(),
    ]);
    $piece->forceFill(['current_status' => HandlingUnitStatus::Loaded])->save();
}

function nextUpGet(Manifest $manifest): TestResponse
{
    return test()->actingAs(User::factory()->create())
        ->getJson(route('loading.manifest.next-up', $manifest));
}

it('requires a signed-in user', function () {
    ['manifest' => $manifest] = nextUpYard();

    $this->getJson(route('loading.manifest.next-up', $manifest))->assertUnauthorized();
});

it('lists ready freight most urgent first, with the reasons', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard(leavesInMinutes: 17);

    $plain = nextUpConsignment($destination, ['connote_number' => 'PLAIN']);
    nextUpPiece($plain, 1, $bay);

    $express = nextUpConsignment($destination, ['connote_number' => 'EXPRESS1', 'service_code' => 'EXPRESS']);
    nextUpPiece($express, 1, $bay);

    $split = nextUpConsignment($destination, ['connote_number' => 'SPLIT1', 'item_count' => 3]);
    nextUpLoad(nextUpPiece($split, 1, null), $manifest);
    nextUpPiece($split, 2, $bay);
    nextUpPiece($split, 3, $bay);

    $response = nextUpGet($manifest)->assertOk();

    expect($response->json('data.*.connote_number'))->toBe(['EXPRESS1', 'SPLIT1', 'PLAIN'])
        ->and($response->json('data.0.tier'))->toBe('P1')
        ->and($response->json('data.0.reasons'))->toBe(['Express service', 'Trailer leaves in 17m'])
        ->and($response->json('data.1.ready'))->toBe(2)
        ->and($response->json('data.1.reasons'))->toBe(['Trailer leaves in 17m', '1 of 3 already on this trailer'])
        ->and($response->json('meta.bay_code'))->toBe('SYD02')
        ->and($response->json('meta.total'))->toBe(3);
});

it('breaks ties by who has waited longest', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard();
    $actor = User::factory()->create();

    $recent = nextUpConsignment($destination, ['connote_number' => 'AAA-RECENT']);
    $old = nextUpConsignment($destination, ['connote_number' => 'ZZZ-OLD']);

    foreach ([[$recent, 20], [$old, 30]] as [$consignment, $minutesAgo]) {
        $piece = nextUpPiece($consignment, 1, $bay);
        OperationalEvent::factory()->create([
            'handling_unit_id' => $piece->getKey(),
            'actor_id' => $actor->getKey(),
            'event_type' => EventType::Staged,
            'occurred_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    // Under an hour: no waiting points, so the scores tie and age decides.
    expect(nextUpGet($manifest)->json('data.*.connote_number'))->toBe(['ZZZ-OLD', 'AAA-RECENT']);
});

it('says how long freight has been waiting in the depot', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard();

    $piece = nextUpPiece(nextUpConsignment($destination), 1, $bay);
    OperationalEvent::factory()->create([
        'handling_unit_id' => $piece->getKey(),
        'event_type' => EventType::Staged,
        'occurred_at' => now()->subHours(5)->subMinutes(10),
    ]);

    nextUpGet($manifest)
        ->assertJsonPath('data.0.reasons', ['Waiting 5h in the depot'])
        ->assertJsonPath('data.0.score', 25);
});

it('leaves out freight that is not ready for this trailer', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard();
    $otherBay = Location::factory()->create([
        'type' => LocationType::Bay,
        'destination_depot_id' => Depot::factory()->create()->getKey(),
    ]);
    $dock = Location::factory()->create(['type' => LocationType::Dock]);

    nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'READY']), 1, $bay);
    nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'AT-DOCK']), 1, $dock);
    nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'NOWHERE']), 1, null);
    nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'WRONG-BAY']), 1, $otherBay);
    nextUpLoad(nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'ALREADY-ON']), 1, $bay), $manifest);

    $other = Manifest::factory()->create(['status' => 'open']);
    nextUpLoad(nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'ON-OTHER']), 1, $bay), $other);

    nextUpGet($manifest)
        ->assertJsonPath('data.*.connote_number', ['READY'])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('meta.held_count', 0);
});

it('excludes held-back and holding-lane freight and counts it', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard();
    $lane = Location::factory()->create(['code' => 'HOLD2', 'type' => LocationType::HoldingArea]);
    $admin = User::factory()->create();

    nextUpPiece(nextUpConsignment($destination, ['connote_number' => 'READY']), 1, $bay);

    $heldBack = nextUpConsignment($destination, ['connote_number' => 'HELD-BACK', 'held_back_at' => now(), 'held_back_by' => $admin->getKey()]);
    nextUpPiece($heldBack, 1, $bay);

    $inLane = nextUpConsignment($destination, ['connote_number' => 'IN-LANE']);
    nextUpPiece($inLane, 1, $lane, ['current_status' => HandlingUnitStatus::Held]);

    // Some pieces held and some ready: the ready ones are listed, and it is not counted as held.
    $mixed = nextUpConsignment($destination, ['connote_number' => 'MIXED', 'item_count' => 2]);
    nextUpPiece($mixed, 1, $bay);
    nextUpPiece($mixed, 2, $lane, ['current_status' => HandlingUnitStatus::Held]);

    nextUpGet($manifest)
        ->assertJsonPath('data.*.connote_number', ['MIXED', 'READY'])
        ->assertJsonPath('meta.held_count', 2);
});

it('shows the tier and ignores the departure when the manifest has none', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard(leavesInMinutes: null);
    nextUpPiece(nextUpConsignment($destination), 1, $bay);

    nextUpGet($manifest)
        ->assertJsonPath('data.0.tier', 'P4')
        ->assertJsonPath('data.0.reasons', [])
        ->assertJsonPath('meta.departs_at', null);
});

it('flags dangerous goods and food among the ready pieces', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard();
    $consignment = nextUpConsignment($destination, ['item_count' => 2]);
    nextUpPiece($consignment, 1, $bay, ['dg_class' => '8']);
    nextUpPiece($consignment, 2, $bay, ['is_food' => true]);

    nextUpGet($manifest)
        ->assertJsonPath('data.0.dg_classes', ['8'])
        ->assertJsonPath('data.0.has_food', true);
});

it('limits the list and reports how many there are', function () {
    ['manifest' => $manifest, 'destination' => $destination, 'bay' => $bay] = nextUpYard();

    foreach (range(1, 23) as $n) {
        nextUpPiece(nextUpConsignment($destination), 1, $bay);
    }

    $response = nextUpGet($manifest)->assertOk();

    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('meta.total'))->toBe(23)
        ->and($response->json('meta.shown'))->toBe(20);
});

it('is empty when the destination has no bay', function () {
    $manifest = Manifest::factory()->create(['status' => 'open']);
    $manifest->destinations()->attach(Depot::factory()->create(), ['is_primary' => true]);

    nextUpGet($manifest)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.bay_code', null);
});
