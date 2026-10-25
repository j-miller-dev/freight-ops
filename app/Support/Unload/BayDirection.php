<?php

namespace App\Support\Unload;

use App\Models\Location;

/**
 * Where a runner should take a pallet, and the reason shown on the screen.
 * `suggested` is set when the runner placed it somewhere other than the
 * suggestion; `overrideReason` says why (a full lane unless stated otherwise).
 */
final readonly class BayDirection
{
    public function __construct(
        public Location $location,
        public string $reason,
        public bool $isHolding,
        public ?int $maxHoldDays = null,
        public ?int $lane = null,
        public ?Location $suggested = null,
        public ?string $overrideReason = null,
    ) {}
}
