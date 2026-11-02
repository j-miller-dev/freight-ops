<?php

namespace App\Support\Unload;

/**
 * Whether a held pallet still deserves to sit, and why. Mirrors
 * App\Support\Priority\PriorityScore's shape.
 */
final readonly class HoldingReadinessStatus
{
    /**
     * @param  'overdue'|'opportunity'|'settled'  $status
     * @param  list<string>  $reasons
     */
    public function __construct(
        public string $status,
        public array $reasons,
    ) {}
}
