<?php

use App\Actions\Unload\MoveHandlingUnit;
use App\Enums\LocationType;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\HoldingRule;
use App\Models\Location;
use App\Models\User;
use App\Notifications\HoldingLaneOverdue;
use App\Support\Unload\BayDirection;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** A pallet held past a 3-day limit, for a sender with a matching hold rule. */
function overdueTestPallet(?CarbonInterface $heldSince = null): HandlingUnit
{
    $home = Depot::factory()->create(['code' => 'MEL']);
    config(['freight.home_depot_code' => 'MEL']);
    $destination = Depot::factory()->create(['code' => 'SYD']);
    $lane = Location::factory()->create([
        'depot_id' => $home->getKey(),
        'code' => 'HOLD1',
        'type' => LocationType::HoldingArea,
        'lane' => 1,
    ]);
    HoldingRule::factory()->create(['sender_name' => 'Harbourline Hardware', 'max_hold_days' => 3]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'sender_name' => 'Harbourline Hardware',
        'item_count' => 1,
    ]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();

    app(MoveHandlingUnit::class)->handle(
        handlingUnit: $pallet,
        direction: new BayDirection(
            location: $lane,
            reason: 'Held for test',
            isHolding: true,
            maxHoldDays: 3,
            lane: $lane->lane,
        ),
        runner: User::factory()->create(),
        clientEventId: (string) Str::uuid(),
        occurredAt: $heldSince ?? now()->subDays(4),
    );

    return $pallet->refresh();
}

it('alerts scalers, supervisors and admins about an overdue pallet', function () {
    Notification::fake();
    overdueTestPallet();
    $scaler = User::factory()->scaler()->create();
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $admin = User::factory()->create(['role' => 'admin']);
    $operator = User::factory()->create();

    $this->artisan('holding-lanes:alert-overdue')->assertSuccessful();

    Notification::assertSentTo([$scaler, $supervisor, $admin], HoldingLaneOverdue::class);
    Notification::assertNotSentTo([$operator], HoldingLaneOverdue::class);
});

it('does not alert about a pallet that is not yet overdue', function () {
    Notification::fake();
    overdueTestPallet(now()->subDay());
    User::factory()->scaler()->create();

    $this->artisan('holding-lanes:alert-overdue')->assertSuccessful();

    Notification::assertNothingSent();
});

it('only alerts once for the same stint of being held', function () {
    $scaler = User::factory()->scaler()->create();
    overdueTestPallet();

    $this->artisan('holding-lanes:alert-overdue')->assertSuccessful();
    $this->artisan('holding-lanes:alert-overdue')->assertSuccessful();

    expect($scaler->notifications()->where('type', HoldingLaneOverdue::class)->count())->toBe(1);
});
