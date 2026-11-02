<?php

namespace App\Support\Unload;

/**
 * What is known about one held pallet's freight, and nothing else. Mirrors
 * App\Support\Priority\PriorityFacts for the opposite question: not "how
 * urgent is this to load" but "has deliberately slowing this down overstayed
 * its welcome."
 */
final readonly class HoldingPalletFacts
{
    public function __construct(
        public ?int $daysHeld,
        public ?int $maxHoldDays,
        // Minutes until the soonest open manifest serving this pallet's
        // destination departs; null when there is none.
        public ?int $minutesToNearestDeparture,
        public ?string $nearestDepartureCode,
    ) {}
}
