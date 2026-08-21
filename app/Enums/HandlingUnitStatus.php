<?php

namespace App\Enums;

enum HandlingUnitStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Staged = 'staged';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case Exception = 'exception';
    case Held = 'held';
}
