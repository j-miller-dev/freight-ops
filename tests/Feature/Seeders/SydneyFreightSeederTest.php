<?php

use App\Models\Manifest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('numbers the seeded manifests like upstream: MEL-SYD-{yymmdd}-{run}', function () {
    $numbers = Manifest::query()->where('source', 'seed')->orderBy('external_id')->pluck('manifest_number');

    expect($numbers)->toHaveCount(7)
        ->and($numbers->unique())->toHaveCount(7)
        ->and($numbers->every(fn (string $number): bool => preg_match('/^MEL-SYD-'.today()->format('ymd').'-[1-7]$/', $number) === 1))->toBeTrue();
});

it('seeds the departed manifest as closed by a scaler, with a close summary', function () {
    $closed = Manifest::query()->where('source', 'seed')->where('status', 'closed')->sole();

    expect($closed->closed_at)->not->toBeNull()
        ->and($closed->closedBy?->role?->value)->toBe('scaler')
        ->and($closed->loading_finished_at)->not->toBeNull()
        ->and($closed->close_summary)->toMatchArray([
            'consignments_total' => 0,
            'loading_finished' => true,
            'closed_with_warnings' => false,
        ]);
});

it('leaves the open seeded manifests without a close record', function () {
    $open = Manifest::query()->where('source', 'seed')->where('status', 'open')->get();

    expect($open)->toHaveCount(6)
        ->and($open->every(fn (Manifest $manifest): bool => $manifest->closed_by === null && $manifest->close_summary === null))->toBeTrue();
});

it('can be seeded twice without duplicating manifests', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Manifest::query()->where('source', 'seed')->count())->toBe(7)
        ->and(User::query()->where('role', 'scaler')->count())->toBe(1);
});
