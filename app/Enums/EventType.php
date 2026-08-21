<?php

namespace App\Enums;

enum EventType: string
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
}
