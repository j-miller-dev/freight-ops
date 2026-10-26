<?php

use App\Support\Priority\PriorityCalculator;
use App\Support\Priority\PriorityFacts;
use App\Support\Priority\PriorityScore;

/** @param  array<string, mixed>  $overrides */
function priorityFacts(array $overrides = []): PriorityFacts
{
    return new PriorityFacts(...($overrides + [
        'serviceCode' => 'STANDARD',
        'minutesToDeparture' => 600,
        'itemCount' => 4,
        'onThisTrailer' => 0,
        'onOtherTrailers' => 0,
        'minutesWaiting' => 0,
    ]));
}

function priorityOf(array $overrides = []): PriorityScore
{
    return (new PriorityCalculator)->score(priorityFacts($overrides));
}

it('scores plain standard freight on a distant trailer as the lowest tier', function () {
    $priority = priorityOf();

    expect($priority->score)->toBe(20)
        ->and($priority->tier)->toBe('P4')
        ->and($priority->reasons)->toBe([]);
});

it('ranks the service levels express, standard, economy', function () {
    $express = priorityOf(['serviceCode' => 'EXPRESS']);
    $standard = priorityOf(['serviceCode' => 'STANDARD']);
    $economy = priorityOf(['serviceCode' => 'ECONOMY']);

    expect($express->score)->toBeGreaterThan($standard->score)
        ->and($standard->score)->toBeGreaterThan($economy->score)
        ->and($express->reasons)->toBe(['Express service'])
        ->and($economy->reasons)->toBe(['Economy service']);
});

it('treats a missing or unknown service code as standard', function () {
    expect(priorityOf(['serviceCode' => null])->score)->toBe(20)
        ->and(priorityOf(['serviceCode' => 'OVERNIGHT?'])->score)->toBe(20)
        ->and(priorityOf(['serviceCode' => 'express'])->score)->toBe(40);
});

it('adds more points the closer the trailer is to leaving', function () {
    $scores = collect([600, 120, 60, 30])->map(fn (int $minutes) => priorityOf(['minutesToDeparture' => $minutes])->score);

    expect($scores->all())->toBe([20, 30, 40, 50]);
});

it('explains the departure and shows hours and minutes', function () {
    expect(priorityOf(['minutesToDeparture' => 17])->reasons)->toBe(['Trailer leaves in 17m'])
        ->and(priorityOf(['minutesToDeparture' => 90])->reasons)->toBe(['Trailer leaves in 1h 30m'])
        ->and(priorityOf(['minutesToDeparture' => 120])->reasons)->toBe(['Trailer leaves in 2h']);
});

it('treats a trailer past its departure time as the most urgent departure', function () {
    $priority = priorityOf(['minutesToDeparture' => -5]);

    expect($priority->score)->toBe(50)
        ->and($priority->reasons)->toBe(['Trailer is past its departure time']);
});

it('ignores the departure when the manifest has none', function () {
    expect(priorityOf(['minutesToDeparture' => null])->score)->toBe(20);
});

it('puts finishing a split on this trailer above a split elsewhere', function () {
    $here = priorityOf(['onThisTrailer' => 2]);
    $elsewhere = priorityOf(['onOtherTrailers' => 2]);

    expect($here->score)->toBe(40)
        ->and($here->reasons)->toBe(['2 of 4 already on this trailer'])
        ->and($elsewhere->score)->toBe(30)
        ->and($elsewhere->reasons)->toBe(['Split: 2 already on other trailers']);
});

it('counts a split once, preferring the pieces already on this trailer', function () {
    $priority = priorityOf(['onThisTrailer' => 1, 'onOtherTrailers' => 3]);

    expect($priority->score)->toBe(40)
        ->and($priority->reasons)->toBe(['1 of 4 already on this trailer']);
});

it('adds a point for each hour waiting, up to ten', function () {
    expect(priorityOf(['minutesWaiting' => 59])->score)->toBe(20)
        ->and(priorityOf(['minutesWaiting' => 60])->score)->toBe(21)
        ->and(priorityOf(['minutesWaiting' => 5 * 60])->score)->toBe(25)
        ->and(priorityOf(['minutesWaiting' => 40 * 60])->score)->toBe(30)
        ->and(priorityOf(['minutesWaiting' => 5 * 60 + 30])->reasons)->toBe(['Waiting 5h in the depot']);
});

it('ignores waiting time that is not known', function () {
    expect(priorityOf(['minutesWaiting' => null])->score)->toBe(20);
});

it('puts an express order on a trailer about to leave in the top tier', function () {
    $priority = priorityOf(['serviceCode' => 'EXPRESS', 'minutesToDeparture' => 17]);

    expect($priority->score)->toBe(70)
        ->and($priority->tier)->toBe('P1')
        ->and($priority->reasons)->toBe(['Express service', 'Trailer leaves in 17m']);
});

it('maps scores to tiers at their boundaries', function (int $minutesWaiting, string $serviceCode, int $score, string $tier) {
    $priority = priorityOf([
        'serviceCode' => $serviceCode,
        'minutesToDeparture' => 600,
        'minutesWaiting' => $minutesWaiting,
    ]);

    expect($priority->score)->toBe($score)->and($priority->tier)->toBe($tier);
})->with([
    'economy, nothing else' => [0, 'ECONOMY', 5, 'P4'],
    'just under P3' => [9 * 60, 'STANDARD', 29, 'P4'],
    'exactly P3' => [10 * 60, 'STANDARD', 30, 'P3'],
    'express, waiting 9h' => [9 * 60, 'EXPRESS', 49, 'P3'],
    'exactly P2' => [10 * 60, 'EXPRESS', 50, 'P2'],
]);

it('never scores above 100', function () {
    $priority = priorityOf([
        'serviceCode' => 'EXPRESS',
        'minutesToDeparture' => 0,
        'onThisTrailer' => 3,
        'minutesWaiting' => 100 * 60,
    ]);

    expect($priority->score)->toBe(100)->and($priority->tier)->toBe('P1');
});
