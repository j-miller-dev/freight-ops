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
        ->assertJsonPath('data.barcode', $pallet->barcode);

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
