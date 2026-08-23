<?php

use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires every pallet barcode to be unique', function () {
    HandlingUnit::factory()->create([
        'barcode' => 'PALLET-000001',
    ]);

    expect(fn () => HandlingUnit::factory()->create([
        'barcode' => 'PALLET-000001',
    ]))->toThrow(QueryException::class);
});

it('has at most one current manifest assignment', function () {
    $pallet = HandlingUnit::factory()->create();

    ManifestItem::factory()
        ->for($pallet, 'handlingUnit')
        ->for(Manifest::factory())
        ->create();

    expect(fn () => ManifestItem::factory()
        ->for($pallet, 'handlingUnit')
        ->for(Manifest::factory())
        ->create())->toThrow(QueryException::class);
});

it('exposes its current manifest through its assignment', function () {
    $manifest = Manifest::factory()->create();
    $pallet = HandlingUnit::factory()->create();

    ManifestItem::factory()
        ->for($manifest)
        ->for($pallet, 'handlingUnit')
        ->create();

    expect($pallet->fresh()->currentManifest?->is($manifest))->toBeTrue();
});
