<?php

use App\Calculators\DgSegregationChecker;

it('flags classes 6.1 and 8 when food is on board', function () {
    $conflicts = (new DgSegregationChecker)->foodConflicts(['8', '3', '6.1', '8'], hasFood: true);

    expect($conflicts)->toBe(['6.1', '8']);
});

it('reports nothing without food', function () {
    expect((new DgSegregationChecker)->foodConflicts(['8', '6.1'], hasFood: false))->toBe([]);
});

it('ignores classes that may travel with food', function () {
    expect((new DgSegregationChecker)->foodConflicts(['3', '9', null], hasFood: true))->toBe([]);
});
