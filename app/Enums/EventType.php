<?php

namespace App\Enums;

enum EventType: string implements HasLabel
{
    case Received = 'received';
    case Moved = 'moved';
    case Staged = 'staged';
    case Loaded = 'loaded';
    case Unloaded = 'unloaded';
    case Dispatched = 'dispatched';
    case ExceptionReported = 'exception_reported';
    case DgVerified = 'dg_verified';
    case EquipmentAssigned = 'equipment_assigned';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Moved => 'Moved',
            self::Staged => 'Staged',
            self::Loaded => 'Loaded',
            self::Unloaded => 'Unloaded',
            self::Dispatched => 'Dispatched',
            self::ExceptionReported => 'Exception Reported',
            self::DgVerified => 'DG Verified',
            self::EquipmentAssigned => 'Equipment Assigned',
        };
    }
}
