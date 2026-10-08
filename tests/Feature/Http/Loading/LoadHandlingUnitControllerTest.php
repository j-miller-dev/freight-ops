<?php

use App\Enums\HandlingUnitStatus;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function loadingManifest(Depot $destination, string $status = 'open'): Manifest
{
    $manifest = Manifest::factory()->create(['status' => $status]);
    $manifest->destinations()->attach($destination, ['is_primary' => true]);

    return $manifest;
}

function loadingPallet(Depot $destination): HandlingUnit
{
    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
    ]);

    return HandlingUnit::factory()
        ->for($consignment)
        ->create(['current_status' => HandlingUnitStatus::Pending]);
}

function scanPayload(HandlingUnit $pallet, ?string $clientEventId = null): array
{
    return [
        'barcode' => $pallet->barcode,
        'client_event_id' => $clientEventId ?? (string) Str::uuid(),
        'occurred_at' => now()->toISOString(),
    ];
}

it('loads a pallet through the HTTP endpoint', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $manifest = loadingManifest($destination);
    $pallet = loadingPallet($destination);

    $response = $this->actingAs($loader)->postJson(
        route('loading.scan', $manifest),
        scanPayload($pallet),
    );

    $response
        ->assertCreated()
        ->assertJsonPath('data.manifest_id', $manifest->getKey())
        ->assertJsonPath('data.handling_unit_id', $pallet->getKey())
        ->assertJsonPath('data.barcode', $pallet->barcode)
        ->assertJsonPath('data.piece_number', $pallet->piece_number)
        ->assertJsonPath('data.connote_number', $pallet->consignment->connote_number)
        ->assertJsonPath('data.consignment_progress.loaded_count', 1)
        ->assertJsonPath('data.consignment_progress.total_count', $pallet->consignment->item_count);

    expect(ManifestItem::query()->count())->toBe(1)
        ->and(OperationalEvent::query()->count())->toBe(1);
});

it('returns an unassigned pending pallet for simulated scans', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $manifest = loadingManifest($destination);
    $pallet = loadingPallet($destination);

    $this->actingAs($loader)
        ->getJson(route('loading.simulate-scan', $manifest))
        ->assertOk()
        ->assertJsonPath('data.barcode', $pallet->barcode)
        ->assertJsonPath('data.connote_number', $pallet->consignment->connote_number);
});

it('includes closed manifests in the picker with their closed status', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $openManifest = loadingManifest($destination, 'open');
    $closedManifest = loadingManifest($destination, 'closed');

    $response = $this->actingAs($loader)->getJson(route('loading.manifests', [
        'destination_id' => $destination->getKey(),
    ]));

    $response->assertOk()
        ->assertJsonPath('data.0.id', $openManifest->getKey())
        ->assertJsonPath('data.0.status', 'open')
        ->assertJsonPath('data.1.id', $closedManifest->getKey())
        ->assertJsonPath('data.1.status', 'closed');
});

it('rejects an invalid scan payload before reaching the action', function () {
    $loader = User::factory()->create();
    $manifest = loadingManifest(Depot::factory()->create());

    $response = $this->actingAs($loader)->postJson(
        route('loading.scan', $manifest),
        [],
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'barcode',
            'client_event_id',
            'occurred_at',
        ]);
});

it('translates a closed manifest into a loading conflict response', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $manifest = loadingManifest($destination, 'closed');
    $pallet = loadingPallet($destination);

    $response = $this->actingAs($loader)->postJson(
        route('loading.scan', $manifest),
        scanPayload($pallet),
    );

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'manifest_not_open');
});

it('returns the original result when an HTTP scan is retried', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $manifest = loadingManifest($destination);
    $pallet = loadingPallet($destination);
    $payload = scanPayload($pallet);

    $firstResponse = $this->actingAs($loader)->postJson(
        route('loading.scan', $manifest),
        $payload,
    );
    $retryResponse = $this->actingAs($loader)->postJson(
        route('loading.scan', $manifest),
        $payload,
    );

    $firstResponse->assertCreated();
    $retryResponse
        ->assertCreated()
        ->assertJsonPath('data.manifest_item_id', $firstResponse->json('data.manifest_item_id'));

    expect(ManifestItem::query()->count())->toBe(1)
        ->and(OperationalEvent::query()->count())->toBe(1);
});

it('rejects reusing a client event for a different HTTP scan', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $firstManifest = loadingManifest($destination);
    $secondManifest = loadingManifest($destination);
    $firstPallet = loadingPallet($destination);
    $secondPallet = loadingPallet($destination);
    $clientEventId = (string) Str::uuid();

    $this->actingAs($loader)->postJson(
        route('loading.scan', $firstManifest),
        scanPayload($firstPallet, $clientEventId),
    )->assertCreated();

    $this->actingAs($loader)->postJson(
        route('loading.scan', $secondManifest),
        scanPayload($secondPallet, $clientEventId),
    )->assertStatus(409)
        ->assertJsonPath('error.code', 'client_event_conflict');

    expect(ManifestItem::query()->count())->toBe(1)
        ->and(OperationalEvent::query()->count())->toBe(1);
});

it('returns previous assignment details before allowing an override', function () {
    $loader = User::factory()->create(['name' => 'Original Loader']);
    $destination = Depot::factory()->create();
    $firstManifest = loadingManifest($destination);
    $secondManifest = loadingManifest($destination);
    $pallet = loadingPallet($destination);

    $this->actingAs($loader)->postJson(
        route('loading.scan', $firstManifest),
        scanPayload($pallet),
    )->assertCreated();

    $response = $this->actingAs(User::factory()->create(['name' => 'Current Loader']))
        ->postJson(
            route('loading.scan', $secondManifest),
            scanPayload($pallet),
        );

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'handling_unit_already_assigned')
        ->assertJsonPath('error.details.previous_manifest_number', $firstManifest->manifest_number)
        ->assertJsonPath('error.details.previous_loader_name', 'Original Loader')
        ->assertJsonPath('error.details.selected_manifest_number', $secondManifest->manifest_number);
});

it('omits yesterday manifests by default', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $yesterdayManifest = Manifest::factory()->create(['service_date' => today()->subDay(), 'status' => 'open']);
    $yesterdayManifest->destinations()->attach($destination, ['is_primary' => true]);

    $response = $this->actingAs($loader)->getJson(route('loading.manifests', [
        'destination_id' => $destination->getKey(),
    ]));

    $response->assertOk()->assertJsonCount(0, 'data');
});

it('includes yesterday manifests when requested', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $yesterdayManifest = Manifest::factory()->create(['service_date' => today()->subDay(), 'status' => 'open']);
    $yesterdayManifest->destinations()->attach($destination, ['is_primary' => true]);

    $response = $this->actingAs($loader)->getJson(route('loading.manifests', [
        'destination_id' => $destination->getKey(),
        'include_yesterday' => 1,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $yesterdayManifest->getKey());
});

it('shows consignment split details', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $selectedManifest = loadingManifest($destination);
    $conflictManifest = loadingManifest($destination);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 2,
    ]);

    $firstPallet = HandlingUnit::factory()
        ->for($consignment)
        ->create(['current_status' => HandlingUnitStatus::Pending]);
    $secondPallet = HandlingUnit::factory()
        ->for($consignment)
        ->create(['current_status' => HandlingUnitStatus::Pending]);

    $this->actingAs($loader)->postJson(
        route('loading.scan', $conflictManifest),
        scanPayload($firstPallet),
    )->assertCreated();

    $response = $this->actingAs($loader)->postJson(
        route('loading.scan', $selectedManifest),
        scanPayload($secondPallet),
    );

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'consignment_split')
        ->assertJsonPath('error.details.total_count', 2)
        ->assertJsonPath('error.details.on_selected_after_scan', 1)
        ->assertJsonPath('error.details.conflicts.0.manifest_number', $conflictManifest->manifest_number)
        ->assertJsonPath('error.details.conflicts.0.pallet_count', 1);
});

it('shows destination mismatch details', function () {
    $loader = User::factory()->create();
    $manifestDestination = Depot::factory()->create(['code' => 'SYD', 'name' => 'Sydney']);
    $palletDestination = Depot::factory()->create(['code' => 'MEL', 'name' => 'Melbourne']);
    $manifest = loadingManifest($manifestDestination);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $palletDestination->getKey(),
        'item_count' => 1,
    ]);
    $pallet = HandlingUnit::factory()
        ->for($consignment)
        ->create(['current_status' => HandlingUnitStatus::Pending]);

    $response = $this->actingAs($loader)->postJson(
        route('loading.scan', $manifest),
        scanPayload($pallet),
    );

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'destination_mismatch')
        ->assertJsonPath('error.details.destination_code', 'MEL')
        ->assertJsonPath('error.details.destination_name', 'Melbourne')
        ->assertJsonPath('error.details.manifest_number', $manifest->manifest_number);
});

it('counts only this consignment when reporting pallets on the selected manifest', function () {
    $loader = User::factory()->create();
    $destination = Depot::factory()->create();
    $selectedManifest = loadingManifest($destination);
    $conflictManifest = loadingManifest($destination);

    // Unrelated freight already on the selected manifest must not inflate the count.
    foreach (range(1, 3) as $ignored) {
        ManifestItem::factory()->create([
            'manifest_id' => $selectedManifest->getKey(),
            'handling_unit_id' => loadingPallet($destination)->getKey(),
        ]);
    }

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 3,
    ]);
    [$onSelected, $onConflict, $toScan] = HandlingUnit::factory()
        ->count(3)
        ->for($consignment)
        ->create(['current_status' => HandlingUnitStatus::Pending]);

    ManifestItem::factory()->create(['manifest_id' => $selectedManifest->getKey(), 'handling_unit_id' => $onSelected->getKey()]);
    ManifestItem::factory()->create(['manifest_id' => $conflictManifest->getKey(), 'handling_unit_id' => $onConflict->getKey()]);

    $this->actingAs($loader)
        ->postJson(route('loading.scan', $selectedManifest), scanPayload($toScan))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'consignment_split')
        ->assertJsonPath('error.details.on_selected_after_scan', 2);
});
