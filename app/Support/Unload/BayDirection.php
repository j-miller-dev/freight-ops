<?php

namespace App\Support\Unload;

use App\Models\Location;

/**
 * Where a runner should take a pallet, and the reason shown on the screen.
 */
final readonly class BayDirection
{
    public function __construct(
        public Location $location,
        public string $reason,
        public bool $isHolding,
        public ?int $maxHoldDays = null,
    ) {}
}
