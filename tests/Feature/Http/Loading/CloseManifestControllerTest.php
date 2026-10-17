<?php

use App\Models\Consignment;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function readyManifest(): Manifest
{
    $manifest = Manifest::factory()->create([
        'status' => 'open',
        'loading_finished_at' => now(),
    ]);
    $consignment = Consignment::factory()->create(['item_count' => 1]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $pallet->getKey(),
    ]);

    return $manifest;
}

it('lets a scaler close a fully reconciled manifest', function () {
    $manifest = readyManifest();
    $scaler = User::factory()->scaler()->create();

    $this->actingAs($scaler)
        ->postJson(route('loading.manifest.close', $manifest))
        ->assertOk()
        ->assertJsonPath('data.status', 'closed')
        ->assertJsonPath('data.closed_by', $scaler->name);

    expect($manifest->refresh()->status)->toBe('closed');
});

it('forbids an operator from closing a manifest', function () {
    $manifest = readyManifest();

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.manifest.close', $manifest))
        ->assertForbidden();

    expect($manifest->refresh()->status)->toBe('open');
});

it('rejects a guest', function () {
    $this->postJson(route('loading.manifest.close', readyManifest()))
        ->assertUnauthorized();
});

it('warns with details instead of closing when consignments are outstanding', function () {
    $manifest = Manifest::factory()->create([
        'status' => 'open',
        'loading_finished_at' => now(),
    ]);
    // Only 1 of 2 pallets for this consignment are on the trailer.
    $consignment = Consignment::factory()->create(['item_count' => 2]);
    $loaded = HandlingUnit::factory()->for($consignment)->create(['piece_number' => 1]);
    HandlingUnit::factory()->for($consignment)->create(['piece_number' => 2]);
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $loaded->getKey(),
    ]);

    $this->actingAs(User::factory()->scaler()->create())
        ->postJson(route('loading.manifest.close', $manifest))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'manifest_not_ready_to_close')
        ->assertJsonPath('error.details.consignments_total', 1)
        ->assertJsonPath('error.details.consignments_complete', 0)
        ->assertJsonPath('error.details.loading_finished', true);

    expect($manifest->refresh()->status)->toBe('open');
});

it('closes anyway when the warning is acknowledged', function () {
    $manifest = Manifest::factory()->create(['status' => 'open', 'loading_finished_at' => null]);

    $this->actingAs(User::factory()->scaler()->create())
        ->postJson(route('loading.manifest.close', $manifest), ['acknowledged' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    expect($manifest->refresh()->close_summary)->toMatchArray(['closed_with_warnings' => true]);
});
