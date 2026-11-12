<?php

use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\ManifestPresence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function seenOn(Manifest $manifest, User $user, int $secondsAgo): void
{
    ManifestPresence::query()->insert([
        'manifest_id' => $manifest->getKey(),
        'user_id' => $user->getKey(),
        'last_seen_at' => now()->subSeconds($secondsAgo),
        'created_at' => now()->subSeconds($secondsAgo),
        'updated_at' => now()->subSeconds($secondsAgo),
    ]);
}

it('records the caller and reports nobody else when alone', function () {
    $manifest = Manifest::factory()->create();
    $me = User::factory()->create();

    $this->actingAs($me)
        ->postJson(route('loading.manifest.presence', $manifest))
        ->assertOk()
        ->assertExactJson(['data' => []]);

    expect(ManifestPresence::query()->where('user_id', $me->getKey())->count())->toBe(1);
});

it('lists other people recently on the manifest, with what they loaded', function () {
    $manifest = Manifest::factory()->create();
    $me = User::factory()->create();
    $sam = User::factory()->create(['name' => 'Sam Scaler']);
    seenOn($manifest, $sam, 10);
    ManifestItem::factory()->count(3)->create([
        'manifest_id' => $manifest->getKey(),
        'loaded_by' => $sam->getKey(),
    ]);

    $this->actingAs($me)
        ->postJson(route('loading.manifest.presence', $manifest))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $sam->getKey())
        ->assertJsonPath('data.0.name', 'Sam Scaler')
        ->assertJsonPath('data.0.loaded_count', 3);
});

it('drops people whose last heartbeat is stale', function () {
    $manifest = Manifest::factory()->create();
    $me = User::factory()->create();
    seenOn($manifest, User::factory()->create(), ManifestPresence::ACTIVE_FOR_SECONDS + 30);

    $this->actingAs($me)
        ->postJson(route('loading.manifest.presence', $manifest))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('only counts people on the same manifest', function () {
    $manifest = Manifest::factory()->create();
    $elsewhere = Manifest::factory()->create();
    seenOn($elsewhere, User::factory()->create(), 5);

    $this->actingAs(User::factory()->create())
        ->postJson(route('loading.manifest.presence', $manifest))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('keeps one row per person however many heartbeats they send', function () {
    $manifest = Manifest::factory()->create();
    $me = User::factory()->create();

    $this->actingAs($me)->postJson(route('loading.manifest.presence', $manifest))->assertOk();
    $this->actingAs($me)->postJson(route('loading.manifest.presence', $manifest))->assertOk();

    expect(ManifestPresence::query()->count())->toBe(1);
});
