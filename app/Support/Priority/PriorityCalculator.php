<?php

namespace App\Support\Priority;

/**
 * How urgent it is to load a consignment's ready freight onto this trailer.
 * Priority is a projection of facts, never stored: the score is the sum of a
 * few capped contributions, each of which also writes its reason.
 *
 * The weights are deliberately plain constants so they are easy to read, argue
 * about and change.
 */
class PriorityCalculator
{
    /** @var array<string, int> Points for the service level. Unknown counts as standard. */
    private const SERVICE_POINTS = [
        'EXPRESS' => 40,
        'STANDARD' => 20,
        'ECONOMY' => 5,
    ];

    /** Points when the trailer leaves within this many minutes, tightest first. */
    private const DEPARTURE_POINTS = [
        30 => 30,
        60 => 20,
        120 => 10,
    ];

    /** Part of the order is already on this trailer: finish it. */
    private const FINISH_SPLIT_POINTS = 20;

    /** Part of the order went on another trailer. */
    private const SPLIT_ELSEWHERE_POINTS = 10;

    /** One point for each hour waiting, up to this many. */
    private const WAITING_MAX_POINTS = 10;

    /** @var array<string, int> The lowest score for each tier. */
    private const TIERS = ['P1' => 70, 'P2' => 50, 'P3' => 30];

    public function score(PriorityFacts $facts): PriorityScore
    {
        $score = 0;
        $reasons = [];

        $service = strtoupper($facts->serviceCode ?? '');
        $service = array_key_exists($service, self::SERVICE_POINTS) ? $service : 'STANDARD';
        $score += self::SERVICE_POINTS[$service];

        if ($service !== 'STANDARD') {
            $reasons[] = ucfirst(strtolower($service)).' service';
        }

        if ($facts->minutesToDeparture !== null) {
            foreach (self::DEPARTURE_POINTS as $withinMinutes => $points) {
                if ($facts->minutesToDeparture <= $withinMinutes) {
                    $score += $points;
                    $reasons[] = $facts->minutesToDeparture < 0
                        ? 'Trailer is past its departure time'
                        : 'Trailer leaves in '.$this->duration($facts->minutesToDeparture);

                    break;
                }
            }
        }

        if ($facts->onThisTrailer > 0) {
            $score += self::FINISH_SPLIT_POINTS;
            $reasons[] = "{$facts->onThisTrailer} of {$facts->itemCount} already on this trailer";
        } elseif ($facts->onOtherTrailers > 0) {
            $score += self::SPLIT_ELSEWHERE_POINTS;
            $reasons[] = "Split: {$facts->onOtherTrailers} already on other trailers";
        }

        if ($facts->minutesWaiting !== null && $facts->minutesWaiting >= 60) {
            $hours = intdiv($facts->minutesWaiting, 60);
            $score += min($hours, self::WAITING_MAX_POINTS);
            $reasons[] = "Waiting {$hours}h in the depot";
        }

        $score = min($score, 100);

        return new PriorityScore($score, $this->tier($score), $reasons);
    }

    private function tier(int $score): string
    {
        foreach (self::TIERS as $tier => $minimum) {
            if ($score >= $minimum) {
                return $tier;
            }
        }

        return 'P4';
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
