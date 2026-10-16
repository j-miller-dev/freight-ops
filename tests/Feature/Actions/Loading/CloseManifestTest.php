<?php

use App\Actions\Loading\CloseManifest;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Exceptions\Loading\ManifestNotReadyToClose;
use App\Models\Consignment;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function loadOnePalletConsignment(Manifest $manifest): Consignment
{
    $consignment = Consignment::factory()->create(['item_count' => 1]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $pallet->getKey(),
    ]);

    return $consignment;
}

/** A two-pallet consignment with only the first piece loaded on this manifest. */
function loadPartialConsignment(Manifest $manifest): Consignment
{
    $consignment = Consignment::factory()->create(['item_count' => 2]);
    $loaded = HandlingUnit::factory()->for($consignment)->create(['piece_number' => 1]);
    HandlingUnit::factory()->for($consignment)->create(['piece_number' => 2]);
    ManifestItem::factory()->create([
        'manifest_id' => $manifest->getKey(),
        'handling_unit_id' => $loaded->getKey(),
    ]);

    return $consignment;
}

it('closes a fully reconciled, finished manifest without needing acknowledgement', function () {
    $manifest = Manifest::factory()->create([
        'status' => 'open',
        'loading_finished_at' => now(),
    ]);
    loadOnePalletConsignment($manifest);
    $scaler = User::factory()->scaler()->create();

    $closed = app(CloseManifest::class)->handle($manifest, $scaler);

    expect($closed->status)->toBe('closed')
        ->and($closed->closed_at)->not->toBeNull()
        ->and($closed->closed_by)->toBe($scaler->getKey())
        ->and($closed->close_summary)->toMatchArray([
            'consignments_total' => 1,
            'consignments_complete' => 1,
            'loading_finished' => true,
            'closed_with_warnings' => false,
        ]);
});

it('refuses to close with outstanding consignments unless acknowledged', function () {
    $manifest = Manifest::factory()->create([
        'status' => 'open',
        'loading_finished_at' => now(),
    ]);
    // Only 1 of 2 pallets for this consignment are on the trailer.
    loadPartialConsignment($manifest);

    $scaler = User::factory()->scaler()->create();

    expect(fn () => app(CloseManifest::class)->handle($manifest, $scaler))
        ->toThrow(ManifestNotReadyToClose::class);

    $manifest->refresh();
    expect($manifest->status)->toBe('open');
});

it('refuses to close when loading was never marked finished', function () {
    $manifest = Manifest::factory()->create(['status' => 'open', 'loading_finished_at' => null]);
    loadOnePalletConsignment($manifest);
    $scaler = User::factory()->scaler()->create();

    expect(fn () => app(CloseManifest::class)->handle($manifest, $scaler))
        ->toThrow(ManifestNotReadyToClose::class);
});

it('closes anyway when the warning is acknowledged, recording the override', function () {
    $manifest = Manifest::factory()->create(['status' => 'open', 'loading_finished_at' => null]);
    $scaler = User::factory()->scaler()->create();

    $closed = app(CloseManifest::class)->handle($manifest, $scaler, acknowledged: true);

    expect($closed->status)->toBe('closed')
        ->and($closed->close_summary)->toMatchArray([
            'consignments_total' => 0,
            'consignments_complete' => 0,
            'loading_finished' => false,
            'closed_with_warnings' => true,
        ]);
});

it('refuses to close a manifest that is already closed', function () {
    $manifest = Manifest::factory()->create(['status' => 'closed', 'closed_at' => now()]);
    $scaler = User::factory()->scaler()->create();

    expect(fn () => app(CloseManifest::class)->handle($manifest, $scaler, acknowledged: true))
        ->toThrow(ManifestNotOpen::class);
});
