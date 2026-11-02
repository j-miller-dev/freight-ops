<?php

namespace App\Support\Unload;

/**
 * Whether a pallet sitting in a holding lane still deserves to. A pure
 * projection of facts, never stored, mirroring App\Support\Priority\PriorityCalculator:
 *
 * - overdue: past the sender's hold-rule limit. An objective breach, not a
 *   judgement call.
 * - opportunity: a trailer to its destination is leaving soon anyway, so it
 *   is worth catching that ride instead of waiting out the clock.
 * - settled: neither. Fine to keep waiting.
 */
class HoldingReadiness
{
    /** A departure within this many minutes counts as a ride worth catching. */
    private const OPPORTUNITY_WINDOW_MINUTES = 60;

    public function forPallet(HoldingPalletFacts $facts): HoldingReadinessStatus
    {
        if (
            $facts->daysHeld !== null
            && $facts->maxHoldDays !== null
            && $facts->daysHeld >= $facts->maxHoldDays
        ) {
            return new HoldingReadinessStatus('overdue', [
                "Held {$facts->daysHeld}d, past the {$facts->maxHoldDays}-day limit",
            ]);
        }

        if (
            $facts->minutesToNearestDeparture !== null
            && $facts->minutesToNearestDeparture <= self::OPPORTUNITY_WINDOW_MINUTES
        ) {
            $when = $facts->minutesToNearestDeparture < 0
                ? 'is past its departure time'
                : 'leaves in '.$this->duration($facts->minutesToNearestDeparture);

            return new HoldingReadinessStatus('opportunity', [
                "Trailer to {$facts->nearestDepartureCode} {$when}",
            ]);
        }

        return new HoldingReadinessStatus('settled', []);
    }

    private function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $remainder = $minutes % 60;

        return intdiv($minutes, 60).'h'.($remainder > 0 ? " {$remainder}m" : '');
    }
}
