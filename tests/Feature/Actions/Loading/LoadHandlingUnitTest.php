<?php

use App\Actions\Loading\LoadHandlingUnit;
use App\Enums\HandlingUnitStatus;
use App\Exceptions\Loading\DestinationMismatch;
use App\Exceptions\Loading\HandlingUnitAlreadyAssigned;
use App\Exceptions\Loading\ManifestNotOpen;
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

it('rejects a pallet scan when the manifest is closed', function () {
    $destination = Depot::factory()->create();
    $closedAt = now()->subMinute();

    $manifest = Manifest::factory()->create([
        'status' => 'closed',
        'closed_at' => $closedAt,
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

    expect(fn () => app(LoadHandlingUnit::class)->handle(
        manifest: $manifest,
        handlingUnit: $pallet,
        loader: $loader,
        clientEventId: (string) Str::uuid(),
        occurredAt: now(),
    ))->toThrow(ManifestNotOpen::class);

    expect(ManifestItem::query()->count())->toBe(0)
        ->and($pallet->fresh()->current_status)
        ->toBe(HandlingUnitStatus::Pending);
});

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

it('reports a conflict when the pallet belongs to another manifest', function () {
    $destination = Depot::factory()->create();

    $existingManifest = Manifest::factory()->create([
        'status' => 'open',
    ]);
    $existingManifest->destinations()->attach($destination, [
        'is_primary' => true,
    ]);

    $selectedManifest = Manifest::factory()->create([
        'status' => 'open',
    ]);
    $selectedManifest->destinations()->attach($destination, [
        'is_primary' => true,
    ]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
    ]);

    $pallet = HandlingUnit::factory()
        ->for($consignment)
        ->create();

    $originalLoader = User::factory()->create();
    $secondLoader = User::factory()->create();
    $action = app(LoadHandlingUnit::class);

    $existingAssignment = $action->handle(
        manifest: $existingManifest,
        handlingUnit: $pallet,
        loader: $originalLoader,
        clientEventId: (string) Str::uuid(),
        occurredAt: now()->subMinute(),
    );

    $caughtException = null;

    try {
        $action->handle(
            manifest: $selectedManifest,
            handlingUnit: $pallet,
            loader: $secondLoader,
            clientEventId: (string) Str::uuid(),
            occurredAt: now(),
        );
    } catch (HandlingUnitAlreadyAssigned $exception) {
        $caughtException = $exception;
    }

    expect($caughtException)->not->toBeNull()
        ->and($caughtException->existingAssignment->is($existingAssignment))->toBeTrue()
        ->and($caughtException->selectedManifest->is($selectedManifest))->toBeTrue()
        ->and(ManifestItem::query()->count())->toBe(1)
        ->and($pallet->fresh()->currentManifest->is($existingManifest))->toBeTrue();
});

it('reports a destination mismatch without loading the pallet', function () {
    $manifestDestination = Depot::factory()->create([
        'code' => 'SYD02',
    ]);

    $palletDestination = Depot::factory()->create([
        'code' => 'ADL02',
    ]);

    $manifest = Manifest::factory()->create([
        'status' => 'open',
    ]);
    $manifest->destinations()->attach($manifestDestination, [
        'is_primary' => true,
    ]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $palletDestination->getKey(),
        'item_count' => 1,
    ]);

    $pallet = HandlingUnit::factory()
        ->for($consignment)
        ->create([
            'current_status' => HandlingUnitStatus::Pending,
        ]);

    $loader = User::factory()->create();
    $caughtException = null;

    try {
        app(LoadHandlingUnit::class)->handle(
            manifest: $manifest,
            handlingUnit: $pallet,
            loader: $loader,
            clientEventId: (string) Str::uuid(),
            occurredAt: now(),
        );
    } catch (DestinationMismatch $exception) {
        $caughtException = $exception;
    }

    expect($caughtException)->not->toBeNull()
        ->and($caughtException->palletDestination->is($palletDestination))->toBeTrue()
        ->and($caughtException->selectedManifest->is($manifest))->toBeTrue()
        ->and(ManifestItem::query()->count())->toBe(0)
        ->and($pallet->fresh()->current_status)
        ->toBe(HandlingUnitStatus::Pending);
});
