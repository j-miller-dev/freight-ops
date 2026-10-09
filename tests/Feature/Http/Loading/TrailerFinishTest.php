<?php

use App\Models\Depot;
use App\Models\Manifest;
use App\Models\ManifestEquipment;
use App\Models\Trailer;
use App\Models\User;
use App\Notifications\ManifestLoadingFinished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function finishManifest(array $attributes = []): Manifest
{
    $manifest = Manifest::factory()->create(['status' => 'open'] + $attributes);
    $manifest->destinations()->attach(Depot::factory()->create(), ['is_primary' => true]);

    return $manifest;
}

it('shows the trailer, equipment and finish state on the manifest page', function () {
    $trailer = Trailer::factory()->contractor('Dingo Creek Haulage')->create();
    $manifest = finishManifest(['trailer_id' => $trailer->getKey()]);
    ManifestEquipment::query()->create(['manifest_id' => $manifest->getKey(), 'item' => 'red_pallets', 'quantity' => 3]);

    $this->actingAs(User::factory()->create())
        ->get(route('loading.manifest', $manifest))
        ->assertInertia(fn (Assert $page) => $page
            ->component('loading/manifest')
            ->where('trailer.name', 'Dingo Creek Haulage')
            ->where('trailer.owner', 'contractor')
            ->where('equipment.red_pallets', 3)
            ->where('equipment.blue_pallets', 0)
            ->where('finished', null)
            ->has('equipment_items', 8));
});

it('lists the trailer on each manifest', function () {
    $trailer = Trailer::factory()->create(['name' => 'F-Ops 2']);
    $manifest = finishManifest(['trailer_id' => $trailer->getKey()]);
    $destination = $manifest->destinations()->first();

    $this->actingAs(User::factory()->create())
        ->getJson(route('loading.manifests', ['destination_id' => $destination->getKey()]).'&include_yesterday=1')
        ->assertOk()
        ->assertJsonPath('data.0.trailer.name', 'F-Ops 2');
});

it('adds to and takes from an equipment count, never below zero', function () {
    $manifest = finishManifest();
    $url = route('loading.manifest.equipment.adjust', $manifest);
    $this->actingAs(User::factory()->create());

    $this->postJson($url, ['item' => 'red_pallets', 'delta' => 3])->assertOk()->assertJsonPath('data.red_pallets', 3);
    $this->postJson($url, ['item' => 'red_pallets', 'delta' => -1])->assertJsonPath('data.red_pallets', 2);
    $this->postJson($url, ['item' => 'red_pallets', 'delta' => -5])->assertJsonPath('data.red_pallets', 0);
    $this->postJson($url, ['item' => 'blue_pallets', 'delta' => 1])->assertJsonPath('data.blue_pallets', 1);
});

it('rejects an unknown item or a zero change', function () {
    $manifest = finishManifest();
    $this->actingAs(User::factory()->create());

    $this->postJson(route('loading.manifest.equipment.adjust', $manifest), ['item' => 'jetpack', 'delta' => 1])->assertUnprocessable();
    $this->postJson(route('loading.manifest.equipment.adjust', $manifest), ['item' => 'straps', 'delta' => 0])->assertUnprocessable();
});

it('finishes a trailer, records the equipment and tells scalers, supervisors and admins', function () {
    Notification::fake();
    $loader = User::factory()->create(['name' => 'Jason Miller']);
    $scaler = User::factory()->scaler()->create();
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $admin = User::factory()->create(['role' => 'admin']);
    $otherLoader = User::factory()->create();
    $manifest = finishManifest();

    $this->actingAs($loader)
        ->postJson(route('loading.manifest.finish', $manifest), ['equipment' => ['straps' => 12, 'red_pallets' => 4, 'jetpack' => 9]])
        ->assertOk()
        ->assertJsonPath('data.finished.by', 'Jason Miller')
        ->assertJsonPath('data.equipment.straps', 12)
        ->assertJsonPath('data.equipment.red_pallets', 4);

    expect($manifest->fresh()->loading_finished_at)->not->toBeNull()
        ->and($manifest->fresh()->loading_finished_by)->toBe($loader->getKey());

    Notification::assertSentTo([$scaler, $supervisor, $admin], ManifestLoadingFinished::class);
    Notification::assertNotSentTo([$loader, $otherLoader], ManifestLoadingFinished::class);
});

it('only alerts once, however many times the counts are edited', function () {
    Notification::fake();
    $scaler = User::factory()->scaler()->create();
    $manifest = finishManifest();
    $this->actingAs(User::factory()->create());

    $this->postJson(route('loading.manifest.finish', $manifest), ['equipment' => ['straps' => 1]])->assertOk();
    $this->postJson(route('loading.manifest.finish', $manifest), ['equipment' => ['straps' => 2]])
        ->assertJsonPath('data.equipment.straps', 2);

    Notification::assertSentToTimes($scaler, ManifestLoadingFinished::class, 1);
});

it('rejects a negative equipment count', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.manifest.finish', finishManifest()), ['equipment' => ['straps' => -1]])
        ->assertUnprocessable();
});

it('reopens a finished trailer', function () {
    $manifest = finishManifest();
    $this->actingAs(User::factory()->create());

    $this->postJson(route('loading.manifest.finish', $manifest), ['equipment' => ['straps' => 1]])->assertOk();
    $this->deleteJson(route('loading.manifest.reopen', $manifest))
        ->assertOk()
        ->assertJsonPath('data.finished', null);

    expect($manifest->fresh()->loading_finished_at)->toBeNull();
});

it('gives each user their own notifications to read', function () {
    $scaler = User::factory()->scaler()->create();
    $other = User::factory()->scaler()->create();
    $manifest = finishManifest();

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.manifest.finish', $manifest), ['equipment' => ['red_pallets' => 2]])
        ->assertOk();

    $this->actingAs($scaler);

    $list = $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('unread_count', 1);
    $id = $list->json('data.0.id');
    expect($list->json('data.0.data.manifest_number'))->toBe($manifest->manifest_number);

    $this->actingAs($other)->postJson(route('notifications.read', $id))->assertNotFound();

    $this->actingAs($scaler)->postJson(route('notifications.read', $id))->assertJsonPath('unread_count', 0);
    $this->postJson(route('notifications.read-all'))->assertJsonPath('unread_count', 0);
});
