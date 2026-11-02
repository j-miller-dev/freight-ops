<?php

use App\Actions\Unload\ReleaseHandlingUnit;
use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Exceptions\Unload\HandlingUnitNotHeld;
use App\Models\HandlingUnit;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('releases a held pallet, recording who and when', function () {
    $pallet = HandlingUnit::factory()->create(['current_status' => HandlingUnitStatus::Held]);
    $supervisor = User::factory()->create();
    $now = now();

    $released = app(ReleaseHandlingUnit::class)->handle($pallet, $supervisor, $now);

    expect($released->released_from_hold_at)->not->toBeNull()
        ->and($released->released_from_hold_at->equalTo($now))->toBeTrue()
        ->and($released->released_from_hold_by)->toBe($supervisor->getKey());

    $event = OperationalEvent::query()->where('event_type', EventType::ReleasedFromHold)->sole();
    expect($event->handling_unit_id)->toBe($pallet->getKey())
        ->and($event->actor_id)->toBe($supervisor->getKey());
});

it('refuses to release a pallet that is not currently held', function () {
    $pallet = HandlingUnit::factory()->create(['current_status' => HandlingUnitStatus::Staged]);

    expect(fn () => app(ReleaseHandlingUnit::class)->handle($pallet, User::factory()->create(), now()))
        ->toThrow(HandlingUnitNotHeld::class);

    expect($pallet->refresh()->released_from_hold_at)->toBeNull();
});

it('is idempotent: releasing an already-released pallet writes no second event', function () {
    $pallet = HandlingUnit::factory()->create(['current_status' => HandlingUnitStatus::Held]);
    $supervisor = User::factory()->create();

    app(ReleaseHandlingUnit::class)->handle($pallet, $supervisor, now());
    $pallet->refresh();
    $firstReleasedAt = $pallet->released_from_hold_at;

    app(ReleaseHandlingUnit::class)->handle($pallet, User::factory()->create(), now()->addMinute());

    expect(OperationalEvent::query()->where('event_type', EventType::ReleasedFromHold)->count())->toBe(1)
        ->and($pallet->refresh()->released_from_hold_at->equalTo($firstReleasedAt))->toBeTrue();
});
