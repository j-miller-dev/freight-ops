<?php

use App\Models\Consignment;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('contains individually identified pallets', function () {
    $consignment = Consignment::factory()->create([
        'item_count' => 3,
    ]);

    $pallets = HandlingUnit::factory()
        ->count(3)
        ->for($consignment)
        ->create();

    expect($consignment->handlingUnits()->pluck('id')->all())
        ->toEqualCanonicalizing($pallets->modelKeys());
});

it('derives loaded progress from current pallet assignments', function () {
    $consignment = Consignment::factory()->create([
        'item_count' => 3,
    ]);
    $pallets = HandlingUnit::factory()
        ->count(3)
        ->for($consignment)
        ->create();

    ManifestItem::factory()
        ->for($pallets->first(), 'handlingUnit')
        ->create();

    expect($consignment->fresh()->loadedCount())->toBe(1)
        ->and($consignment->fresh()->item_count)->toBe(3);
});

it('allows different pallets in one consignment to be split across manifests', function () {
    $consignment = Consignment::factory()->create([
        'item_count' => 2,
    ]);
    $pallets = HandlingUnit::factory()
        ->count(2)
        ->for($consignment)
        ->create();
    $manifests = Manifest::factory()->count(2)->create();

    ManifestItem::factory()
        ->for($manifests->first())
        ->for($pallets->first(), 'handlingUnit')
        ->create();
    ManifestItem::factory()
        ->for($manifests->last())
        ->for($pallets->last(), 'handlingUnit')
        ->create();

    expect($consignment->manifestItems()->pluck('manifest_id')->unique()->all())
        ->toHaveCount(2);
});
