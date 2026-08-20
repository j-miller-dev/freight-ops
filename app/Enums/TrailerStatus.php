<?php

namespace App\Enums;

enum TrailerStatus: string
{
    case Available = 'available';
    case Loading = 'loading';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case AtDock = 'at_dock';
}
