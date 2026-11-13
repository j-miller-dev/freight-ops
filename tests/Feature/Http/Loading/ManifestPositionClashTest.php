<?php

use App\Enums\LoadWarningType;
use App\Enums\TrailerType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\ManifestUnitLabel;
use App\Models\User;
use App\Models\WarningAcknowledgement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A trailer with unit A labelled as carrying food, and a class 8 pallet loaded but not yet placed. */
function foodOnAWithClass8(): array
{
    $manifest = Manifest::factory()->create(['status' => 'open', 'trailer_type' => TrailerType::BDouble]);
    $manifest->destinations()->attach(Depot::factory()->create(), ['is_primary' => true]);

    $label = new ManifestUnitLabel;
    $label->manifest_id = $manifest->getKey();
    $label->unit = 1;
    $label->category = 'food';
    $label->save();

    $pallet = HandlingUnit::factory()
        ->for(Consignment::factory()->create(['item_count' => 1]))
        ->create(['dg_class' => '8']);
    ManifestItem::factory()->create(['manifest_id' => $manifest->getKey(), 'handling_unit_id' => $pallet->getKey()]);

    return [$manifest, $pallet];
}

it('asks for confirmation before placing a pallet on a unit it must not share', function () {
    [$manifest, $pallet] = foodOnAWithClass8();

    $this->actingAs(User::factory()->create())
        ->patchJson(route('loading.manifest.position', [$manifest, $pallet]), ['unit' => 1, 'row' => 1, 'side' => 'D'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'segregation_clash')
        ->assertJsonPath('error.details.issues.0.status', 'clash')
        ->assertJsonPath('error.details.issues.0.unit_names', ['A']);

    expect(ManifestItem::query()->where('handling_unit_id', $pallet->getKey())->value('trailer_unit'))->toBeNull()
        ->and(WarningAcknowledgement::query()->count())->toBe(0);
});

it('places it and records who acknowledged the clash when confirmed', function () {
    [$manifest, $pallet] = foodOnAWithClass8();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('loading.manifest.position', [$manifest, $pallet]), [
            'unit' => 1,
            'row' => 1,
            'side' => 'D',
            'acknowledged_warnings' => ['segregation_clash'],
        ])
        ->assertOk()
        ->assertJsonPath('data.position', ['unit' => 1, 'row' => 1, 'side' => 'D']);

    $acknowledgement = WarningAcknowledgement::query()->sole();

    expect($acknowledgement->warning_type)->toBe(LoadWarningType::SegregationClash)
        ->and($acknowledgement->acknowledged_by)->toBe($user->getKey())
        ->and($acknowledgement->handling_unit_id)->toBe($pallet->getKey())
        ->and($acknowledgement->metadata['placement']['unit'])->toBe(1)
        ->and($acknowledgement->metadata['issues'][0]['status'])->toBe('clash');
});

it('places it without a prompt on the unit that is clear of the food', function () {
    [$manifest, $pallet] = foodOnAWithClass8();

    $this->actingAs(User::factory()->create())
        ->patchJson(route('loading.manifest.position', [$manifest, $pallet]), ['unit' => 2, 'row' => 1, 'side' => 'D'])
        ->assertOk();

    expect(WarningAcknowledgement::query()->count())->toBe(0);
});

it('never prompts when taking a pallet off the map', function () {
    [$manifest, $pallet] = foodOnAWithClass8();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('loading.manifest.position', [$manifest, $pallet]), [
            'unit' => 1, 'row' => 1, 'side' => 'D', 'acknowledged_warnings' => ['segregation_clash'],
        ])
        ->assertOk();

    $this->actingAs($user)
        ->patchJson(route('loading.manifest.position', [$manifest, $pallet]), ['unit' => null, 'row' => null, 'side' => null])
        ->assertOk()
        ->assertJsonPath('data.position', null);
});

it('does not re-ask when moving within a unit that already has the clash', function () {
    [$manifest, $pallet] = foodOnAWithClass8();
    $user = User::factory()->create();
    $ack = ['segregation_clash'];

    $this->actingAs($user)->patchJson(route('loading.manifest.position', [$manifest, $pallet]), [
        'unit' => 1, 'row' => 1, 'side' => 'D', 'acknowledged_warnings' => $ack,
    ])->assertOk();

    // Same unit, different slot: nothing new is being introduced.
    $this->actingAs($user)
        ->patchJson(route('loading.manifest.position', [$manifest, $pallet]), ['unit' => 1, 'row' => 2, 'side' => 'P'])
        ->assertOk();
});
