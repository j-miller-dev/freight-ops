<?php

namespace App\Enums;

enum BayType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Flex = 'flex';
}
