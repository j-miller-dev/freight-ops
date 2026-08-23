<?php

use App\Actions\Loading\LoadHandlingUnit;
use App\Enums\HandlingUnitStatus;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('loads an eligible pallet onto an open manifest', function () {
    $destination = Depot::factory()->create();

    $manifest = Manifest::factory()->create([
        'status' => 'open',
    ]);
    $manifest->destinations()->attach($destination, [
        'is_primary' => true,
    ]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
    ]);

    $pallet = HandlingUnit::factory()
        ->for($consignment)
        ->create([
            'current_status' => HandlingUnitStatus::Pending,
        ]);

    $loader = User::factory()->create();
    $clientEventId = (string) Str::uuid();
    $occurredAt = now();

    $manifestItem = app(LoadHandlingUnit::class)->handle(
        manifest: $manifest,
        handlingUnit: $pallet,
        loader: $loader,
        clientEventId: $clientEventId,
        occurredAt: $occurredAt,
    );

    expect($manifestItem->manifest->is($manifest))->toBeTrue()
        ->and($manifestItem->handlingUnit->is($pallet))->toBeTrue()
        ->and($manifestItem->loader->is($loader))->toBeTrue()
        ->and($manifestItem->client_event_id)->toBe($clientEventId)
        ->and($pallet->fresh()->current_status)->toBe(HandlingUnitStatus::Loaded);
});

it('returns the existing assignment when the same pallet is scanned again', function () {
    $destination = Depot::factory()->create();

    $manifest = Manifest::factory()->create([
        'status' => 'open',
    ]);
    $manifest->destinations()->attach($destination, [
        'is_primary' => true,
    ]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
    ]);

    $pallet = HandlingUnit::factory()
        ->for($consignment)
        ->create();

    $firstLoader = User::factory()->create();
    $secondLoader = User::factory()->create();
    $action = app(LoadHandlingUnit::class);

    $originalAssignment = $action->handle(
        manifest: $manifest,
        handlingUnit: $pallet,
        loader: $firstLoader,
        clientEventId: (string) Str::uuid(),
        occurredAt: now()->subMinute(),
    );

    $duplicateResult = $action->handle(
        manifest: $manifest,
        handlingUnit: $pallet,
        loader: $secondLoader,
        clientEventId: (string) Str::uuid(),
        occurredAt: now(),
    );

    expect(ManifestItem::query()->count())->toBe(1)
        ->and($duplicateResult->is($originalAssignment))->toBeTrue()
        ->and($duplicateResult->loader->is($firstLoader))->toBeTrue();
});
