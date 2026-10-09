<?php

use App\Models\Depot;
use App\Models\Manifest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('redirects guests away from the loading pages', function () {
    $depot = Depot::factory()->create();
    $manifest = Manifest::factory()->create();

    $this->get(route('loading.depot', $depot))->assertRedirect(route('login'));
    $this->get(route('loading.manifest', $manifest))->assertRedirect(route('login'));
});

it('renders the manifest picker for an active depot', function () {
    $depot = Depot::factory()->create(['is_active' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('loading.depot', $depot))
        ->assertInertia(fn (Assert $page) => $page
            ->component('loading/depot')
            ->where('destination.id', $depot->getKey())
            ->where('destination.code', $depot->code));
});

it('returns 404 for an inactive depot', function () {
    $depot = Depot::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('loading.depot', $depot))
        ->assertNotFound();
});

it('renders the scan page with the manifest and its primary destination', function () {
    $user = User::factory()->create();
    $primary = Depot::factory()->create();
    $secondary = Depot::factory()->create();
    $manifest = Manifest::factory()->create(['status' => 'open']);
    $manifest->destinations()->attach($secondary, ['is_primary' => false]);
    $manifest->destinations()->attach($primary, ['is_primary' => true]);

    $this->actingAs($user)
        ->get(route('loading.manifest', $manifest))
        ->assertInertia(fn (Assert $page) => $page
            ->component('loading/manifest')
            ->where('manifest.id', $manifest->getKey())
            ->where('manifest.status', 'open')
            ->where('manifest.manifest_items_count', 0)
            ->where('destination.id', $primary->getKey())
            ->where('loader.id', $user->getKey()));
});
