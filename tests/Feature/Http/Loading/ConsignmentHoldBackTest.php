<?php

use App\Enums\HandlingUnitStatus;
use App\Models\Consignment;
use App\Models\ConsignmentHoldBackEvent;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('holds a consignment back with a reason, audited against the actor', function () {
    $consignment = Consignment::factory()->create();
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->patchJson(route('loading.manifest.consignment.hold-back', [
            'manifest' => Manifest::factory()->create(),
            'consignment' => $consignment,
        ]), [
            'held_back' => true,
            'reason' => 'routine store stock, no rush',
        ])
        ->assertOk()
        ->assertJsonPath('data.held_back', true)
        ->assertJsonPath('data.held_back_reason', 'routine store stock, no rush')
        ->assertJsonPath('data.held_back_by', $actor->name);

    $consignment->refresh();

    expect($consignment->isHeldBack())->toBeTrue()
        ->and($consignment->held_back_reason)->toBe('routine store stock, no rush')
        ->and($consignment->held_back_by)->toBe($actor->getKey());

    $event = ConsignmentHoldBackEvent::query()->where('consignment_id', $consignment->getKey())->sole();
    expect($event->held_back)->toBeTrue()
        ->and($event->actor_id)->toBe($actor->getKey())
        ->and($event->reason)->toBe('routine store stock, no rush');
});

it('holds a consignment back without a reason', function () {
    $consignment = Consignment::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patchJson(route('loading.manifest.consignment.hold-back', [
            'manifest' => Manifest::factory()->create(),
            'consignment' => $consignment,
        ]), ['held_back' => true])
        ->assertOk()
        ->assertJsonPath('data.held_back_reason', null);
});

it('releases a held-back consignment and clears the reason', function () {
    $consignment = Consignment::factory()->create([
        'held_back_at' => now(),
        'held_back_by' => User::factory()->create()->getKey(),
        'held_back_reason' => 'no rush',
    ]);

    $this->actingAs(User::factory()->create())
        ->patchJson(route('loading.manifest.consignment.hold-back', [
            'manifest' => Manifest::factory()->create(),
            'consignment' => $consignment,
        ]), ['held_back' => false])
        ->assertOk()
        ->assertJsonPath('data.held_back', false)
        ->assertJsonPath('data.held_back_reason', null)
        ->assertJsonPath('data.held_back_by', null);

    $consignment->refresh();

    expect($consignment->isHeldBack())->toBeFalse()
        ->and($consignment->held_back_reason)->toBeNull()
        ->and($consignment->held_back_by)->toBeNull();

    $event = ConsignmentHoldBackEvent::query()
        ->where('consignment_id', $consignment->getKey())
        ->latest('occurred_at')
        ->sole();
    expect($event->held_back)->toBeFalse();
});

it('requires held_back to be present', function () {
    $consignment = Consignment::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patchJson(route('loading.manifest.consignment.hold-back', [
            'manifest' => Manifest::factory()->create(),
            'consignment' => $consignment,
        ]), [])
        ->assertInvalid(['held_back']);
});

it('rejects a guest', function () {
    $consignment = Consignment::factory()->create();

    $this->patchJson(route('loading.manifest.consignment.hold-back', [
        'manifest' => Manifest::factory()->create(),
        'consignment' => $consignment,
    ]), ['held_back' => true])
        ->assertUnauthorized();
});

it('surfaces held_back on the consignment board and detail', function () {
    $destination = Depot::factory()->create();
    $manifest = Manifest::factory()->create(['status' => 'open']);
    $manifest->destinations()->attach($destination, ['is_primary' => true]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
        'held_back_at' => now(),
        'held_back_by' => User::factory()->create()->getKey(),
        'held_back_reason' => 'no rush',
    ]);
    $pallet = HandlingUnit::factory()->for($consignment)->create(['piece_number' => 1]);
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $pallet->getKey(),
    ]);
    $pallet->forceFill(['current_status' => HandlingUnitStatus::Loaded])->save();

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.manifest.consignments', $manifest))
        ->assertOk()
        ->assertJsonPath('data.0.held_back', true);

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.manifest.consignment', ['manifest' => $manifest, 'consignment' => $consignment]))
        ->assertOk()
        ->assertJsonPath('data.held_back', true)
        ->assertJsonPath('data.held_back_reason', 'no rush');
});
