<?php

namespace App\Enums;

enum LocationType: string
{
    case Dock = 'dock';
    case Bay = 'bay';
    case HoldingArea = 'holding_area';
    case DgArea = 'dg_area';
    case ExceptionArea = 'exception_area';
    case Trailer = 'trailer';
    case Vehicle = 'vehicle';
}
