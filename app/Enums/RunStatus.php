<?php

namespace App\Enums;

enum RunStatus: string
{
    case Planning = 'planning';
    case Loading = 'loading';
    case Ready = 'ready';
    case Departed = 'departed';
    case Cancelled = 'cancelled';
}
