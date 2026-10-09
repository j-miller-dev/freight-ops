<?php

namespace App\Enums;

enum LocationType: string implements HasLabel
{
    case Dock = 'dock';
    case Bay = 'bay';
    case HoldingArea = 'holding_area';
    case DgArea = 'dg_area';
    case ExceptionArea = 'exception_area';
    case Trailer = 'trailer';
    case Vehicle = 'vehicle';

    public function label(): string
    {
        return match ($this) {
            self::Dock => 'Dock',
            self::Bay => 'Bay',
            self::HoldingArea => 'Holding Area',
            self::DgArea => 'DG Area',
            self::ExceptionArea => 'Exception Area',
            self::Trailer => 'Trailer',
            self::Vehicle => 'Vehicle',
        };
    }
}
