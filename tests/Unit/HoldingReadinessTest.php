<?php

use App\Support\Unload\HoldingPalletFacts;
use App\Support\Unload\HoldingReadiness;
use App\Support\Unload\HoldingReadinessStatus;

/** @param  array<string, mixed>  $overrides */
function holdingFacts(array $overrides = []): HoldingPalletFacts
{
    return new HoldingPalletFacts(...($overrides + [
        'daysHeld' => 1,
        'maxHoldDays' => 3,
        'minutesToNearestDeparture' => null,
        'nearestDepartureCode' => null,
    ]));
}

function readinessOf(array $overrides = []): HoldingReadinessStatus
{
    return (new HoldingReadiness)->forPallet(holdingFacts($overrides));
}

it('is settled well within the hold limit with no departure nearby', function () {
    $status = readinessOf();

    expect($status->status)->toBe('settled')
        ->and($status->reasons)->toBe([]);
});

it('is overdue once days held reaches the limit', function () {
    expect(readinessOf(['daysHeld' => 2, 'maxHoldDays' => 3])->status)->toBe('settled')
        ->and(readinessOf(['daysHeld' => 3, 'maxHoldDays' => 3])->status)->toBe('overdue')
        ->and(readinessOf(['daysHeld' => 5, 'maxHoldDays' => 3])->status)->toBe('overdue');
});

it('explains the overdue reason with both numbers', function () {
    expect(readinessOf(['daysHeld' => 4, 'maxHoldDays' => 3])->reasons)
        ->toBe(['Held 4d, past the 3-day limit']);
});

it('cannot be overdue without a matching hold rule', function () {
    expect(readinessOf(['daysHeld' => 10, 'maxHoldDays' => null])->status)->toBe('settled');
});

it('is an opportunity when a trailer to its destination leaves within the hour', function () {
    expect(readinessOf(['minutesToNearestDeparture' => 60, 'nearestDepartureCode' => 'SYD02'])->status)
        ->toBe('opportunity')
        ->and(readinessOf(['minutesToNearestDeparture' => 61])->status)->toBe('settled');
});

it('explains the opportunity reason, including an overdue departure', function () {
    expect(readinessOf(['minutesToNearestDeparture' => 17, 'nearestDepartureCode' => 'SYD02'])->reasons)
        ->toBe(['Trailer to SYD02 leaves in 17m'])
        ->and(readinessOf(['minutesToNearestDeparture' => -5, 'nearestDepartureCode' => 'SYD02'])->reasons)
        ->toBe(['Trailer to SYD02 is past its departure time']);
});

it('prefers overdue over opportunity when both apply', function () {
    $status = readinessOf([
        'daysHeld' => 5,
        'maxHoldDays' => 3,
        'minutesToNearestDeparture' => 10,
        'nearestDepartureCode' => 'SYD02',
    ]);

    expect($status->status)->toBe('overdue');
});
