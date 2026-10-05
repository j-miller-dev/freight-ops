<?php

namespace App\Enums;

enum NotificationEventType: string implements HasLabel
{
    case P1Received = 'p1_received';
    case P1Stale = 'p1_stale';
    case BayNearCapacity = 'bay_near_capacity';
    case BayFull = 'bay_full';
    case FreightAgeWarning = 'freight_age_warning';
    case FreightAgeCritical = 'freight_age_critical';
    case PriorityPromoted = 'priority_promoted';
    case TrailerCutoffWarning = 'trailer_cutoff_warning';
    case RunReady = 'run_ready';
    case ShiftHandover = 'shift_handover';

    public function label(): string
    {
        return match ($this) {
            self::P1Received => 'P1 Received',
            self::P1Stale => 'P1 Stale',
            self::BayNearCapacity => 'Bay Near Capacity',
            self::BayFull => 'Bay Full',
            self::FreightAgeWarning => 'Freight Age Warning',
            self::FreightAgeCritical => 'Freight Age Critical',
            self::PriorityPromoted => 'Priority Promoted',
            self::TrailerCutoffWarning => 'Trailer Cutoff Warning',
            self::RunReady => 'Run Ready',
            self::ShiftHandover => 'Shift Handover',
        };
    }
}
