<?php

namespace App\Enums;

enum ScanType: string
{
    case Receive = 'receive';
    case Load = 'load';
    case Unload = 'unload';
    case BayAssign = 'bay_assign';
    case BayRemove = 'bay_remove';
    case Dispatch = 'dispatch';
    case Exception = 'exception';
}
