<?php

namespace App\Enums;

enum ManifestStatus: string
{
    case Pending = 'pending';
    case Receiving = 'receiving';
    case Complete = 'complete';
    case Dispatched = 'dispatched';
}
