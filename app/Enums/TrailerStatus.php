<?php

namespace App\Enums;

enum TrailerStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Loading = 'loading';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case AtDock = 'at_dock';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Loading => 'Loading',
            self::Loaded => 'Loaded',
            self::Dispatched => 'Dispatched',
            self::InTransit => 'In Transit',
            self::AtDock => 'At Dock',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::Loading => 'blue',
            self::Loaded => 'yellow',
            self::Dispatched => 'purple',
            self::InTransit => 'indigo',
            self::AtDock => 'gray',
        };
    }
}
