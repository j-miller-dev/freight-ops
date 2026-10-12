<?php

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\HoldingRule;
use App\Models\Location;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{home: Depot, destination: Depot, bay: Location, holding: Location}
 */
function unloadYard(): array
{
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);
    $destination = Depot::factory()->create(['code' => 'SYD']);

    return [
        'home' => $home,
        'destination' => $destination,
        'bay' => Location::factory()->create([
            'depot_id' => $home->getKey(),
            'code' => 'SYD02',
            'type' => LocationType::Bay,
            'destination_depot_id' => $destination->getKey(),
        ]),
        'holding' => Location::factory()->create([
            'depot_id' => $home->getKey(),
            'code' => 'HOLD1',
            'type' => LocationType::HoldingArea,
        ]),
    ];
}

function unloadPallet(Depot $destination, ?string $sender = null): HandlingUnit
{
    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'sender_name' => $sender,
        'item_count' => 1,
    ]);

    return HandlingUnit::factory()->for($consignment)->create();
}

function dropPayload(HandlingUnit $pallet, Location $bay, ?string $clientEventId = null): array
{
    return [
        'barcode' => $pallet->barcode,
        'location_id' => $bay->getKey(),
        'client_event_id' => $clientEventId ?? (string) Str::uuid(),
        'occurred_at' => now()->toISOString(),
    ];
}

it('requires a signed-in user', function () {
    $this->postJson(route('unload.direction'), ['barcode' => 'X'])->assertUnauthorized();
});

it('directs a pallet to the bay for its destination', function () {
    $yard = unloadYard();
    $pallet = unloadPallet($yard['destination'], 'Some Sender');

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertOk()
        ->assertJsonPath('data.bay.code', 'SYD02')
        ->assertJsonPath('data.bay.location_id', $yard['bay']->getKey())
        ->assertJsonPath('data.bay.is_holding', false)
        ->assertJsonPath('data.reason', 'Freight for SYD');

    expect(OperationalEvent::query()->count())->toBe(0);
});

it('directs a holding-rule sender to the holding bay with a reason', function () {
    $yard = unloadYard();
    HoldingRule::factory()->create([
        'sender_name' => 'Harbourline Hardware',
        'max_hold_days' => 2,
        'reason' => 'Store replenishment',
    ]);
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertOk()
        ->assertJsonPath('data.bay.code', 'HOLD1')
        ->assertJsonPath('data.bay.is_holding', true)
        ->assertJsonPath('data.bay.max_hold_days', 2)
        ->assertJsonPath('data.reason', 'Store replenishment. Can wait up to 2 days.');
});

it('ignores inactive holding rules', function () {
    $yard = unloadYard();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'is_active' => false]);
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertJsonPath('data.bay.code', 'SYD02');
});

it('falls back to the destination bay when no holding bay exists', function () {
    $yard = unloadYard();
    $yard['holding']->forceFill(['is_active' => false])->save();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware']);
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertJsonPath('data.bay.code', 'SYD02');
});

it('reports a destination with no bay', function () {
    $yard = unloadYard();
    $pallet = unloadPallet(Depot::factory()->create(['code' => 'ADL']));

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'no_bay_for_destination')
        ->assertJsonPath('error.details.destination_code', 'ADL');
});

it('returns 404 for an unknown barcode', function () {
    unloadYard();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => 'NOPE'])
        ->assertNotFound();
});

it('moves the pallet and records one event on confirm', function () {
    $yard = unloadYard();
    $runner = User::factory()->create();
    $pallet = unloadPallet($yard['destination']);

    $this->actingAs($runner)
        ->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))
        ->assertOk()
        ->assertJsonPath('data.bay.code', 'SYD02');

    $pallet->refresh();
    expect($pallet->current_location_id)->toBe($yard['bay']->getKey())
        ->and($pallet->current_status)->toBe(HandlingUnitStatus::Staged);

    $event = OperationalEvent::query()->sole();
    expect($event->event_type)->toBe(EventType::Staged)
        ->and($event->actor_id)->toBe($runner->getKey())
        ->and($event->metadata['to_location_code'])->toBe('SYD02')
        ->and($event->metadata['from_location_id'])->toBeNull();
});

it('marks a pallet held when it goes to the holding bay', function () {
    $yard = unloadYard();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware']);
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $yard['holding']))
        ->assertOk();

    expect($pallet->refresh()->current_status)->toBe(HandlingUnitStatus::Held);
});

it('records a Moved event when the pallet already has a location', function () {
    $yard = unloadYard();
    $pallet = unloadPallet($yard['destination']);
    $pallet->forceFill(['current_location_id' => $yard['holding']->getKey()])->save();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))
        ->assertOk();

    $event = OperationalEvent::query()->sole();
    expect($event->event_type)->toBe(EventType::Moved)
        ->and($event->metadata['from_location_code'])->toBe('HOLD1');
});

it('is idempotent for a repeated client event id', function () {
    $yard = unloadYard();
    $runner = User::factory()->create();
    $pallet = unloadPallet($yard['destination']);
    $payload = dropPayload($pallet, $yard['bay']);

    $this->actingAs($runner)->postJson(route('unload.confirm'), $payload)->assertOk();
    $this->actingAs($runner)->postJson(route('unload.confirm'), $payload)->assertOk();

    expect(OperationalEvent::query()->count())->toBe(1);
});

it('does not record a second event for a different event id when already in the bay', function () {
    $yard = unloadYard();
    $runner = User::factory()->create();
    $pallet = unloadPallet($yard['destination']);

    $this->actingAs($runner)->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))->assertOk();
    $this->actingAs($runner)->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))->assertOk();

    expect(OperationalEvent::query()->count())->toBe(1);
});

it('rejects a client event id reused for another pallet', function () {
    $yard = unloadYard();
    $runner = User::factory()->create();
    $first = unloadPallet($yard['destination']);
    $second = unloadPallet($yard['destination']);
    $eventId = (string) Str::uuid();

    $this->actingAs($runner)->postJson(route('unload.confirm'), dropPayload($first, $yard['bay'], $eventId))->assertOk();
    $this->actingAs($runner)->postJson(route('unload.confirm'), dropPayload($second, $yard['bay'], $eventId))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'client_event_conflict');

    expect($second->refresh()->current_location_id)->toBeNull();
});

it('asks the runner to check when the bay changed since the scan', function () {
    $yard = unloadYard();
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware']);

    // The runner was shown SYD02, but the sender now has a holding rule.
    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'direction_changed')
        ->assertJsonPath('error.details.bay_code', 'HOLD1');

    expect($pallet->refresh()->current_location_id)->toBeNull()
        ->and(OperationalEvent::query()->count())->toBe(0);
});

it('refuses to direct a pallet that is already on a trailer', function () {
    $yard = unloadYard();
    $pallet = unloadPallet($yard['destination']);
    ManifestItem::factory()->create([
        'handling_unit_id' => $pallet->getKey(),
        'manifest_id' => Manifest::factory()->create()->getKey(),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'handling_unit_already_loaded');

    expect($pallet->refresh()->current_location_id)->toBeNull();
});

it('validates the confirm payload', function () {
    unloadYard();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['barcode', 'location_id', 'client_event_id', 'occurred_at']);
});

it('renders the runner page', function () {
    $this->withoutVite();

    $this->actingAs(User::factory()->create())
        ->get(route('unload.index'))
        ->assertInertia(fn (Assert $page) => $page->component('unload/index'));
});

it('shows the drop-off in the pallet trace', function () {
    $yard = unloadYard();
    $runner = User::factory()->create();
    $pallet = unloadPallet($yard['destination']);
    $manifest = Manifest::factory()->create();

    $this->actingAs($runner)->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))->assertOk();

    $this->actingAs($runner)
        ->getJson(route('loading.manifest.consignment', [$manifest, $pallet->consignment]))
        ->assertOk()
        ->assertJsonPath('data.pieces.0.events.0.type', 'staged')
        ->assertJsonPath('data.pieces.0.events.0.location_code', 'SYD02');
});

/**
 * @return array<int, Location> holding lanes 1 to 5, keyed by lane number
 */
function holdingLanes(Depot $home): array
{
    $lanes = [];

    foreach (range(1, 5) as $lane) {
        $lanes[$lane] = Location::factory()->create([
            'depot_id' => $home->getKey(),
            'code' => "HOLD{$lane}",
            'type' => LocationType::HoldingArea,
            'lane' => $lane,
        ]);
    }

    return $lanes;
}

it('picks the holding lane from the sender and the service level', function (?string $service, int $expectedLane) {
    $yard = unloadYard();
    $yard['holding']->delete();
    holdingLanes($yard['home']);
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'base_lane' => 4]);
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');
    $pallet->consignment->forceFill(['service_code' => $service])->save();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertOk()
        ->assertJsonPath('data.bay.is_holding', true)
        ->assertJsonPath('data.bay.lane', $expectedLane)
        ->assertJsonPath('data.bay.code', "HOLD{$expectedLane}");
})->with([
    'standard stays on the base lane' => ['STANDARD', 4],
    'no service stays on the base lane' => [null, 4],
    'economy goes one lane later' => ['ECONOMY', 5],
    'express goes two lanes sooner' => ['EXPRESS', 2],
]);

it('never moves past lane 1 or lane 5', function () {
    $yard = unloadYard();
    $yard['holding']->delete();
    holdingLanes($yard['home']);
    HoldingRule::factory()->create(['sender_name' => 'Early Sender', 'base_lane' => 1]);
    HoldingRule::factory()->create(['sender_name' => 'Late Sender', 'base_lane' => 5]);

    $early = unloadPallet($yard['destination'], 'Early Sender');
    $early->consignment->forceFill(['service_code' => 'EXPRESS'])->save();
    $late = unloadPallet($yard['destination'], 'Late Sender');
    $late->consignment->forceFill(['service_code' => 'ECONOMY'])->save();

    $this->actingAs(User::factory()->create());
    $this->postJson(route('unload.direction'), ['barcode' => $early->barcode])->assertJsonPath('data.bay.lane', 1);
    $this->postJson(route('unload.direction'), ['barcode' => $late->barcode])->assertJsonPath('data.bay.lane', 5);
});

it('uses the nearest lane when the target lane is not set up', function () {
    $yard = unloadYard();
    $yard['holding']->delete();
    $lanes = holdingLanes($yard['home']);
    $lanes[4]->forceFill(['is_active' => false])->save();
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'base_lane' => 4]);
    $pallet = unloadPallet($yard['destination'], 'Harbourline Hardware');

    // Lanes 3 and 5 are equally close; the lower lane wins the tie.
    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertJsonPath('data.bay.lane', 3);
});

it('simulates an unload scan with an unplaced pallet', function () {
    $yard = unloadYard();
    $placed = unloadPallet($yard['destination']);
    $placed->forceFill(['current_location_id' => $yard['bay']->getKey()])->save();
    $unplaced = unloadPallet($yard['destination']);

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.simulate-scan'))
        ->assertOk()
        ->assertJsonPath('data.barcode', $unplaced->barcode);
});

it('skips loaded pallets when simulating an unload scan', function () {
    $yard = unloadYard();
    $pallet = unloadPallet($yard['destination']);
    ManifestItem::factory()->create([
        'handling_unit_id' => $pallet->getKey(),
        'manifest_id' => Manifest::factory()->create()->getKey(),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('unload.simulate-scan'))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'simulation_pallet_unavailable');
});

function heldPalletInLaneYard(): array
{
    $yard = unloadYard();
    $yard['holding']->delete();
    $lanes = holdingLanes($yard['home']);
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'base_lane' => 1]);

    return [$yard, $lanes, unloadPallet($yard['destination'], 'Harbourline Hardware')];
}

it('offers the other holding lanes when a pallet is held', function () {
    [, $lanes, $pallet] = heldPalletInLaneYard();

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertOk()
        ->assertJsonPath('data.bay.lane', 1)
        ->assertJsonCount(5, 'data.alternatives')
        ->assertJsonPath('data.alternatives.1.location_id', $lanes[2]->getKey())
        ->assertJsonPath('data.alternatives.1.lane', 2);
});

it('offers no alternatives for freight going to its destination bay', function () {
    $yard = unloadYard();
    $pallet = unloadPallet($yard['destination']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.direction'), ['barcode' => $pallet->barcode])
        ->assertJsonCount(0, 'data.alternatives');
});

it('lets the runner place a held pallet in another lane when the suggested one is full', function () {
    [, $lanes, $pallet] = heldPalletInLaneYard();
    $runner = User::factory()->create();

    $this->actingAs($runner)
        ->postJson(route('unload.confirm'), dropPayload($pallet, $lanes[2]))
        ->assertOk()
        ->assertJsonPath('data.bay.code', 'HOLD2')
        ->assertJsonPath('data.bay.lane', 2)
        ->assertJsonPath('data.suggested_lane', 1);

    expect($pallet->refresh()->current_location_id)->toBe($lanes[2]->getKey())
        ->and($pallet->current_status)->toBe(HandlingUnitStatus::Held);

    $event = OperationalEvent::query()->sole();
    expect($event->metadata['to_location_code'])->toBe('HOLD2')
        ->and($event->metadata['override_reason'])->toBe('lane_full')
        ->and($event->metadata['suggested_location_code'])->toBe('HOLD1');
});

it('does not mark a normal drop as an override', function () {
    [, $lanes, $pallet] = heldPalletInLaneYard();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $lanes[1]))
        ->assertOk();

    expect(OperationalEvent::query()->sole()->metadata)->not->toHaveKey('override_reason');
});

it('is idempotent when an overridden drop is retried', function () {
    [, $lanes, $pallet] = heldPalletInLaneYard();
    $payload = dropPayload($pallet, $lanes[3]);

    $this->actingAs(User::factory()->create());
    $this->postJson(route('unload.confirm'), $payload)->assertOk();
    $this->postJson(route('unload.confirm'), $payload)->assertOk();

    expect(OperationalEvent::query()->count())->toBe(1);
});

it('rejects an override to an inactive lane', function () {
    [, $lanes, $pallet] = heldPalletInLaneYard();
    $lanes[2]->forceFill(['is_active' => false])->save();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $lanes[2]))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'direction_changed');

    expect($pallet->refresh()->current_location_id)->toBeNull();
});

it('rejects overriding a held pallet into a destination bay', function () {
    [$yard, , $pallet] = heldPalletInLaneYard();

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $yard['bay']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'direction_changed');
});

it('rejects a lane override for freight that is not held', function () {
    $yard = unloadYard();
    $yard['holding']->delete();
    $lanes = holdingLanes($yard['home']);
    $pallet = unloadPallet($yard['destination']);

    $this->actingAs(User::factory()->create())
        ->postJson(route('unload.confirm'), dropPayload($pallet, $lanes[2]))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'direction_changed')
        ->assertJsonPath('error.details.bay_code', 'SYD02');
});
