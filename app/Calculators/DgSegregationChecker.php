<?php

namespace App\Calculators;

/**
 * Decision support for dangerous-goods segregation. Staff stay responsible for
 * the final call; this only surfaces known conflicts so they are hard to miss.
 */
class DgSegregationChecker
{
    /**
     * DG classes the operation says must not travel with food. This is the
     * team's working rule and still needs validating against the ADG Code
     * segregation tables before it is relied on for compliance.
     *
     * @var list<string>
     */
    public const FOOD_INCOMPATIBLE_CLASSES = ['6.1', '8'];

    /**
     * @param  iterable<string|null>  $dgClasses  DG classes present on one trailer
     * @return list<string> the classes that conflict with the food on board
     */
    public function foodConflicts(iterable $dgClasses, bool $hasFood): array
    {
        if (! $hasFood) {
            return [];
        }

        $conflicts = [];

        foreach ($dgClasses as $class) {
            if ($class !== null && in_array($class, self::FOOD_INCOMPATIBLE_CLASSES, true)) {
                $conflicts[$class] = $class;
            }
        }

        $conflicts = array_values($conflicts);
        sort($conflicts, SORT_NATURAL);

        return $conflicts;
    }
}
