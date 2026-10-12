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
