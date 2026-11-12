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
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function scanOffManifest(Depot $destination, string $status = 'open'): Manifest
{
    $manifest = Manifest::factory()->create(['status' => $status]);
    $manifest->destinations()->attach($destination, ['is_primary' => true]);

    return $manifest;
}

/** A pallet already loaded on the manifest, as it would be after a normal scan. */
function loadedOn(Manifest $manifest, Depot $destination, ?Location $location = null): HandlingUnit
{
    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 2,
    ]);

    $pallet = HandlingUnit::factory()->for($consignment)->create([
        'current_status' => HandlingUnitStatus::Loaded,
        'current_location_id' => $location?->getKey(),
    ]);

    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $pallet->getKey(),
    ]);

    return $pallet;
}

function scanOffPayload(HandlingUnit $pallet, ?string $clientEventId = null): array
{
    return [
        'barcode' => $pallet->barcode,
        'client_event_id' => $clientEventId ?? (string) Str::uuid(),
        'occurred_at' => now()->toISOString(),
    ];
}

it('takes a pallet off the manifest and puts it back in its bay', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $bay = Location::factory()->create(['type' => LocationType::Bay]);
    $pallet = loadedOn($manifest, $destination, $bay);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertOk()
        ->assertJsonPath('data.barcode', $pallet->barcode)
        ->assertJsonPath('data.status', 'staged')
        ->assertJsonPath('data.location.code', $bay->code)
        ->assertJsonPath('data.progress.loaded_count', 0);

    $pallet->refresh();

    expect(ManifestItem::query()->count())->toBe(0)
        ->and($pallet->current_status)->toBe(HandlingUnitStatus::Staged)
        ->and($pallet->current_location_id)->toBe($bay->getKey());

    $event = OperationalEvent::query()->sole();

    expect($event->event_type)->toBe(EventType::Unloaded)
        ->and($event->actor_id)->toBe($user->getKey())
        ->and($event->metadata['action'])->toBe('scan_off')
        ->and($event->metadata['to_location_code'])->toBe($bay->code);
});

it('marks a pallet as held when it goes back to a holding lane', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $lane = Location::factory()->create(['type' => LocationType::HoldingArea]);
    $pallet = loadedOn($manifest, $destination, $lane);

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertOk()
        ->assertJsonPath('data.status', 'held');
});

it('finds the last bay from the movement history when the pallet has no location', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $bay = Location::factory()->create(['type' => LocationType::Bay]);
    $pallet = loadedOn($manifest, $destination);

    OperationalEvent::factory()->create([
        'handling_unit_id' => $pallet->getKey(),
        'event_type' => EventType::Staged,
        'occurred_at' => now()->subHour(),
        'metadata' => ['to_location_id' => $bay->getKey(), 'to_location_code' => $bay->code],
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertOk()
        ->assertJsonPath('data.location.code', $bay->code);

    expect($pallet->refresh()->current_location_id)->toBe($bay->getKey());
});

it('leaves a pallet with no history on the dock as received', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $pallet = loadedOn($manifest, $destination);

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertOk()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.location', null);

    expect($pallet->refresh()->current_location_id)->toBeNull();
});

it('names the other manifest when the pallet is on a different trailer', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $other = scanOffManifest($destination);
    $pallet = loadedOn($other, $destination);

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'handling_unit_not_on_manifest')
        ->assertJsonPath('error.details.actual_manifest_number', $other->manifest_number);

    expect(ManifestItem::query()->count())->toBe(1);
});

it('rejects a pallet that was never loaded', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $pallet = HandlingUnit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'handling_unit_not_on_manifest')
        ->assertJsonPath('error.details.actual_manifest_number', null);
});

it('does not let anyone scan off a closed manifest', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination, 'closed');
    $pallet = loadedOn($manifest, $destination);

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'manifest_not_open');

    expect(ManifestItem::query()->count())->toBe(1);
});

it('treats a retried request as the same scan-off', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $bay = Location::factory()->create(['type' => LocationType::Bay]);
    $pallet = loadedOn($manifest, $destination, $bay);
    $payload = scanOffPayload($pallet);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('loading.scan-off', $manifest), $payload)->assertOk();
    $this->actingAs($user)->postJson(route('loading.scan-off', $manifest), $payload)->assertOk();

    expect(OperationalEvent::query()->count())->toBe(1);
});

it('requires a signed-in user', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $pallet = loadedOn($manifest, $destination);

    $this->postJson(route('loading.scan-off', $manifest), scanOffPayload($pallet))
        ->assertUnauthorized();
});

it('simulates a scan-off by picking a pallet that is loaded on the manifest', function () {
    $destination = Depot::factory()->create();
    $manifest = scanOffManifest($destination);
    $pallet = loadedOn($manifest, $destination);
    loadedOn(scanOffManifest($destination), $destination); // on a different trailer

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.simulate-scan-off', $manifest))
        ->assertOk()
        ->assertJsonPath('data.barcode', $pallet->barcode);
});

it('says so when there is nothing loaded to simulate taking off', function () {
    $manifest = scanOffManifest(Depot::factory()->create());

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.simulate-scan-off', $manifest))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'simulation_pallet_unavailable');
});

it('keeps the scan-off simulator off in production unless the flag is on', function () {
    $manifest = scanOffManifest(Depot::factory()->create());
    app()->detectEnvironment(fn () => 'production');

    config(['freight.simulate_scans' => false]);
    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.simulate-scan-off', $manifest))
        ->assertNotFound()
        ->assertJsonMissingPath('error.code');

    config(['freight.simulate_scans' => true]);
    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.simulate-scan-off', $manifest))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'simulation_pallet_unavailable');
});
