<?php

namespace App\Support\Priority;

/**
 * An urgency score with the reasons behind it, so a loader can see why
 * something is at the top and a supervisor can argue with the rules.
 */
final readonly class PriorityScore
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $score,
        public string $tier,
        public array $reasons,
    ) {}
}
