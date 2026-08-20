<?php

namespace App\Enums;

enum LoadSheetStatus: string
{
    case Draft = 'draft';
    case Loading = 'loading';
    case Complete = 'complete';
    case SignedOff = 'signed_off';
}
