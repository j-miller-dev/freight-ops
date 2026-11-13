<?php

use App\Enums\TrailerType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\ManifestUnitLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function labelManifest(string $status = 'open'): Manifest
{
    $manifest = Manifest::factory()->create(['status' => $status, 'trailer_type' => TrailerType::BDouble]);
    $manifest->destinations()->attach(Depot::factory()->create(), ['is_primary' => true]);

    return $manifest;
}

function loadedWith(Manifest $manifest, array $attributes, ?int $unit = null): void
{
    $pallet = HandlingUnit::factory()
        ->for(Consignment::factory()->create(['item_count' => 3]))
        ->create($attributes);

    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $pallet->getKey(),
        'trailer_unit' => $unit,
        'trailer_row' => $unit === null ? null : 1,
        'trailer_side' => $unit === null ? null : 'D',
    ]);
}

it('labels a unit with what it is carrying', function () {
    $manifest = labelManifest();

    $this->actingAs(User::factory()->create())
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['8', 'oil']])
        ->assertOk()
        ->assertJsonPath('data.segregation.units.0.name', 'A')
        ->assertJsonCount(2, 'data.segregation.units.0.categories')
        ->assertJsonCount(0, 'data.segregation.units.1.categories');

    expect(ManifestUnitLabel::query()->where('unit', 1)->pluck('category')->sort()->values()->all())
        ->toBe(['8', 'oil']);
});

it('replaces the unit\'s labels rather than adding to them', function () {
    $manifest = labelManifest();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['8', 'oil']])->assertOk();
    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['food']])->assertOk();

    expect(ManifestUnitLabel::query()->pluck('category')->all())->toBe(['food']);
});

it('clears a unit when given no categories', function () {
    $manifest = labelManifest();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 2]), ['categories' => ['food']])->assertOk();
    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 2]), ['categories' => []])->assertOk();

    expect(ManifestUnitLabel::query()->count())->toBe(0);
});

it('rejects a category it does not know', function () {
    $manifest = labelManifest();

    $this->actingAs(User::factory()->create())
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['banana']])
        ->assertUnprocessable();
});

it('rejects a unit the trailer does not have', function () {
    $manifest = labelManifest(); // B-double: A and B only

    $this->actingAs(User::factory()->create())
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 3]), ['categories' => ['food']])
        ->assertUnprocessable();
});

it('refuses labels on a closed manifest', function () {
    $manifest = labelManifest('closed');

    $this->actingAs(User::factory()->create())
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['food']])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'manifest_not_open');
});

it('shows a clash on the unit when food and class 8 are labelled onto the same unit', function () {
    $manifest = labelManifest();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['food']])->assertOk();

    $this->actingAs($user)
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['food', '8']])
        ->assertOk()
        ->assertJsonPath('data.segregation.issues.0.status', 'clash')
        ->assertJsonPath('data.segregation.issues.0.unit_names', ['A']);
});

it('is satisfied once the two are on different units', function () {
    $manifest = labelManifest();
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['8']])->assertOk();

    $this->actingAs($user)
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 2]), ['categories' => ['food']])
        ->assertOk()
        ->assertJsonCount(0, 'data.segregation.issues');
});

it('turns undecided into resolved when the loader labels where the class 8 is', function () {
    $manifest = labelManifest();
    loadedWith($manifest, ['is_food' => true]);
    loadedWith($manifest, ['dg_class' => '8']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('loading.manifest.summary', $manifest))
        ->assertJsonPath('data.segregation.issues.0.status', 'undecided');

    $this->actingAs($user)
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['8']])
        ->assertOk()
        ->assertJsonCount(0, 'data.segregation.issues');
});

it('counts a DG pallet placed on the trailer map as being on that unit', function () {
    $manifest = labelManifest();
    loadedWith($manifest, ['dg_class' => '8'], unit: 1);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['food']])
        ->assertOk()
        ->assertJsonPath('data.segregation.issues.0.status', 'clash')
        ->assertJsonPath('data.segregation.units.0.categories.0.key', '8');
});

it('does not warn when a new pallet can go on a unit that is clear of it', function () {
    $manifest = labelManifest();
    $user = User::factory()->create();
    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['8']])->assertOk();

    $consignment = Consignment::factory()->create(['destination_depot_id' => $manifest->destinations()->first()->getKey(), 'item_count' => 1]);
    $food = HandlingUnit::factory()->for($consignment)->create(['is_food' => true]);

    $this->actingAs($user)
        ->postJson(route('loading.scan', $manifest), [
            'barcode' => $food->barcode,
            'client_event_id' => (string) Str::uuid(),
            'occurred_at' => now()->toISOString(),
        ])
        ->assertCreated();
});

it('drops labels for units the new trailer layout does not have', function () {
    $manifest = Manifest::factory()->create(['status' => 'open', 'trailer_type' => TrailerType::BTriple]);
    $user = User::factory()->create();

    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 3]), ['categories' => ['food']])->assertOk();
    $this->actingAs($user)->putJson(route('loading.manifest.unit-labels', [$manifest, 1]), ['categories' => ['8']])->assertOk();

    $this->actingAs($user)
        ->patchJson(route('loading.manifest.trailer', $manifest), ['trailer_type' => TrailerType::BDouble->value])
        ->assertOk();

    expect(ManifestUnitLabel::query()->pluck('unit')->all())->toBe([1]);
});
