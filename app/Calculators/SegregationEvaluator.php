<?php

namespace App\Calculators;

/**
 * Decision support for segregation: which pairs of freight on a trailer break
 * a "must not travel together" rule. Staff stay responsible for the final
 * call; this only makes conflicts hard to miss.
 *
 * A rule only applies within one trailer unit (A, B or C), so what matters is
 * where each category is known to be. A category is "pinned" to a unit when a
 * loader labelled the unit with it or a pallet of it was placed on that unit;
 * a pallet that has not been placed could still go on any unit.
 *
 * For a rule whose two categories are both on the trailer:
 *  - clash:       both are pinned to the same unit(s).
 *  - undecided:   neither is pinned yet, so nobody has said which unit is which.
 *  - unavoidable: one is pinned and the other has no unit it could go on.
 * Anything else (both pinned apart, or one pinned with a free unit left) is fine.
 */
class SegregationEvaluator
{
    public const CLASH = 'clash';

    public const UNDECIDED = 'undecided';

    public const UNAVOIDABLE = 'unavoidable';

    /**
     * @param  list<array{a: string, b: string, reason: string|null}>  $rules
     */
    public function __construct(private readonly array $rules) {}

    /**
     * @param  list<string>  $present  every category on the trailer (pallets and labels)
     * @param  array<string, list<int>>  $pinned  category => units it is pinned to
     * @return list<array{a: string, b: string, status: string, units: list<int>, reason: string|null}>
     */
    public function issues(array $present, array $pinned, int $unitCount): array
    {
        $present = array_values(array_unique($present));
        $issues = [];

        foreach ($this->rules as $rule) {
            ['a' => $a, 'b' => $b] = $rule;

            if (! in_array($a, $present, true) || ! in_array($b, $present, true)) {
                continue;
            }

            // One unit means the whole trailer is that unit.
            $pinnedA = $unitCount <= 1 ? [1] : ($pinned[$a] ?? []);
            $pinnedB = $unitCount <= 1 ? [1] : ($pinned[$b] ?? []);
            $shared = array_values(array_intersect($pinnedA, $pinnedB));

            $status = match (true) {
                $shared !== [] => self::CLASH,
                $pinnedA === [] && $pinnedB === [] => self::UNDECIDED,
                $pinnedA === [] => $this->freeUnits($a, $pinned, $unitCount) === [] ? self::UNAVOIDABLE : null,
                $pinnedB === [] => $this->freeUnits($b, $pinned, $unitCount) === [] ? self::UNAVOIDABLE : null,
                default => null,
            };

            if ($status === null) {
                continue;
            }

            $issues[] = [
                'a' => $a,
                'b' => $b,
                'status' => $status,
                'units' => $shared,
                'reason' => $rule['reason'],
            ];
        }

        return $issues;
    }

    /**
     * Units a not-yet-placed category could go on without sharing with anything
     * it must be kept away from.
     *
     * @param  array<string, list<int>>  $pinned
     * @return list<int>
     */
    private function freeUnits(string $category, array $pinned, int $unitCount): array
    {
        $blocked = [];

        foreach ($this->rules as $rule) {
            $partner = match ($category) {
                $rule['a'] => $rule['b'],
                $rule['b'] => $rule['a'],
                default => null,
            };

            if ($partner !== null) {
                $blocked = [...$blocked, ...($pinned[$partner] ?? [])];
            }
        }

        return array_values(array_diff(range(1, max(1, $unitCount)), $blocked));
    }
}
