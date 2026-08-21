<?php

namespace App\Enums;

enum ExceptionStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
}
