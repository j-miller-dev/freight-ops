<?php

use App\Calculators\SegregationEvaluator;

function evaluator(): SegregationEvaluator
{
    return new SegregationEvaluator([
        ['a' => 'food', 'b' => '8', 'reason' => 'Corrosives must not travel with food'],
        ['a' => 'food', 'b' => '6.1', 'reason' => null],
        ['a' => '4.1', 'b' => 'oil', 'reason' => null],
    ]);
}

it('reports nothing when only one side of a rule is on the trailer', function () {
    expect(evaluator()->issues(['8'], ['8' => [1]], 2))->toBe([]);
});

it('reports a clash when both categories are pinned to the same unit', function () {
    $issues = evaluator()->issues(['food', '8'], ['food' => [1], '8' => [1]], 2);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['status'])->toBe('clash')
        ->and($issues[0]['units'])->toBe([1])
        ->and($issues[0]['reason'])->toBe('Corrosives must not travel with food');
});

it('accepts the two being pinned to different units', function () {
    expect(evaluator()->issues(['food', '8'], ['food' => [2], '8' => [1]], 2))->toBe([]);
});

it('is undecided when both are on the trailer but neither is pinned', function () {
    $issues = evaluator()->issues(['food', '8'], [], 2);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['status'])->toBe('undecided')
        ->and($issues[0]['units'])->toBe([]);
});

it('is fine when one is pinned and the other still has a free unit', function () {
    expect(evaluator()->issues(['food', '8'], ['8' => [1]], 2))->toBe([]);
});

it('is unavoidable when the pinned category blocks every unit the other could use', function () {
    $issues = evaluator()->issues(['food', '8'], ['8' => [1, 2]], 2);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['status'])->toBe('unavoidable');
});

it('treats a single-unit trailer as one unit, so anything in conflict is a clash', function () {
    $issues = evaluator()->issues(['food', '6.1'], [], 1);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['status'])->toBe('clash')
        ->and($issues[0]['units'])->toBe([1]);
});

it('keeps class 4.1 away from oil', function () {
    $issues = evaluator()->issues(['4.1', 'oil'], ['4.1' => [1], 'oil' => [1]], 3);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['a'])->toBe('4.1')
        ->and($issues[0]['b'])->toBe('oil');
});

it('reports each broken rule separately', function () {
    $issues = evaluator()->issues(['food', '8', '6.1'], ['food' => [1], '8' => [1], '6.1' => [1]], 2);

    expect($issues)->toHaveCount(2);
});
