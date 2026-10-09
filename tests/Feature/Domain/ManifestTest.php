<?php

use App\Models\Depot;
use App\Models\Manifest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('uniquely identifies an imported manifest by source and external id', function () {
    Manifest::factory()->create([
        'source' => 'fixture',
        'external_id' => 'manifest-123',
    ]);

    expect(fn () => Manifest::factory()->create([
        'source' => 'fixture',
        'external_id' => 'manifest-123',
    ]))->toThrow(QueryException::class);
});

it('can serve more than one destination', function () {
    $manifest = Manifest::factory()->create();
    $destinations = Depot::factory()->count(2)->create();

    $manifest->destinations()->attach($destinations, [
        'is_primary' => false,
    ]);

    expect($manifest->destinations()->pluck('depots.id')->all())
        ->toEqualCanonicalizing($destinations->modelKeys());
});

it('filters open manifests by destination and service date', function () {
    $destination = Depot::factory()->create();
    $otherDestination = Depot::factory()->create();

    $loadable = Manifest::factory()->create([
        'service_date' => today(),
        'status' => 'open',
    ]);
    $loadable->destinations()->attach($destination, ['is_primary' => true]);

    $wrongDestination = Manifest::factory()->create([
        'service_date' => today(),
        'status' => 'open',
    ]);
    $wrongDestination->destinations()->attach($otherDestination, ['is_primary' => true]);

    $closed = Manifest::factory()->create([
        'service_date' => today(),
        'status' => 'closed',
    ]);
    $closed->destinations()->attach($destination, ['is_primary' => true]);

    $manifests = Manifest::query()
        ->availableForLoading($destination, today(), today())
        ->get();

    expect($manifests->modelKeys())->toBe([$loadable->getKey()]);
});
