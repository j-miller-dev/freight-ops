<?php

namespace App\Enums;

enum NotificationEventType: string
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
}
