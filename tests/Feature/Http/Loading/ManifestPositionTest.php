<?php

use App\Enums\TrailerType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @return array{0: Manifest, 1: HandlingUnit, 2: HandlingUnit} */
function positionFixture(): array
{
    $destination = Depot::factory()->create();
    $manifest = Manifest::factory()->create(['status' => 'open', 'trailer_type' => TrailerType::BDouble]);
    $manifest->destinations()->attach($destination, ['is_primary' => true]);
    $consignment = Consignment::factory()->create(['destination_depot_id' => $destination->getKey(), 'item_count' => 2]);

    $pallets = [];

    foreach ([1, 2] as $piece) {
        $pallet = HandlingUnit::factory()->for($consignment)->create(['piece_number' => $piece, 'dg_class' => '8']);
        ManifestItem::factory()->create(['manifest_id' => $manifest->getKey(), 'handling_unit_id' => $pallet->getKey()]);
        $pallets[] = $pallet;
    }

    return [$manifest, $pallets[0], $pallets[1]];
}

it('places a DG pallet on the trailer and reports it in the summary', function () {
    [$manifest, $pallet] = positionFixture();
    $this->actingAs(User::factory()->create());

    $this->patchJson(route('loading.manifest.position', [$manifest, $pallet]), ['unit' => 2, 'row' => 10, 'side' => 'D'])
        ->assertOk()
        ->assertJsonPath('data.position', ['unit' => 2, 'row' => 10, 'side' => 'D']);

    $summary = $this->getJson(route('loading.manifest.summary', $manifest))
        ->assertOk()
        ->json('data');

    expect($summary['consignments_total'])->toBe(1)
        ->and($summary['consignments_complete'])->toBe(1)
        ->and(collect($summary['dg_items'])->firstWhere('id', $pallet->getKey())['position'])
        ->toBe(['unit' => 2, 'row' => 10, 'side' => 'D']);
});

it('clears a position', function () {
    [$manifest, $pallet] = positionFixture();
    $this->actingAs(User::factory()->create());
    $url = route('loading.manifest.position', [$manifest, $pallet]);

    $this->patchJson($url, ['unit' => 1, 'row' => 1, 'side' => 'P'])->assertOk();
    $this->patchJson($url, ['unit' => null, 'row' => null, 'side' => null])
        ->assertOk()
        ->assertJsonPath('data.position', null);

    expect(ManifestItem::query()->where('handling_unit_id', $pallet->getKey())->value('trailer_unit'))->toBeNull();
});

it('refuses a position another pallet already holds', function () {
    [$manifest, $first, $second] = positionFixture();
    $this->actingAs(User::factory()->create());
    $slot = ['unit' => 1, 'row' => 3, 'side' => 'D'];

    $this->patchJson(route('loading.manifest.position', [$manifest, $first]), $slot)->assertOk();

    $this->patchJson(route('loading.manifest.position', [$manifest, $second]), $slot)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'position_taken');

    // Re-saving the same pallet into its own slot is fine.
    $this->patchJson(route('loading.manifest.position', [$manifest, $first]), $slot)->assertOk();
});

it('rejects positions outside the trailer layout', function () {
    [$manifest, $pallet] = positionFixture();
    $this->actingAs(User::factory()->create());
    $url = route('loading.manifest.position', [$manifest, $pallet]);

    // The lead trailer of a B-double has 7 rows, the rear has 10.
    $this->patchJson($url, ['unit' => 1, 'row' => 8, 'side' => 'D'])->assertUnprocessable();
    $this->patchJson($url, ['unit' => 3, 'row' => 1, 'side' => 'D'])->assertUnprocessable();
    $this->patchJson($url, ['unit' => 1, 'row' => 1, 'side' => 'X'])->assertUnprocessable();
    $this->patchJson($url, ['unit' => 1, 'row' => 1])->assertUnprocessable();
});

it('only positions pallets that are on the manifest', function () {
    [$manifest] = positionFixture();
    $stranger = HandlingUnit::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patchJson(route('loading.manifest.position', [$manifest, $stranger]), ['unit' => 1, 'row' => 1, 'side' => 'D'])
        ->assertNotFound();
});

it('clears positions when the trailer type changes', function () {
    [$manifest, $pallet] = positionFixture();
    $this->actingAs(User::factory()->create());

    $this->patchJson(route('loading.manifest.position', [$manifest, $pallet]), ['unit' => 1, 'row' => 1, 'side' => 'D'])->assertOk();

    $this->patchJson(route('loading.manifest.trailer', $manifest), ['trailer_type' => 'b_double'])
        ->assertJsonPath('data.positions_cleared', 0);

    $this->patchJson(route('loading.manifest.trailer', $manifest), ['trailer_type' => 'b_triple'])
        ->assertJsonPath('data.positions_cleared', 1);

    expect(ManifestItem::query()->where('handling_unit_id', $pallet->getKey())->value('trailer_unit'))->toBeNull();
});

it('knows the pallet capacity of each trailer layout', function () {
    expect(TrailerType::BDouble->capacity())->toBe(34)
        ->and(TrailerType::BDouble->rows())->toBe([7, 10])
        ->and(TrailerType::BDouble->isLayoutConfirmed())->toBeTrue()
        ->and(TrailerType::BTriple->isLayoutConfirmed())->toBeFalse();
});
