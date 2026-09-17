<?php

namespace App\Enums;

enum RunStatus: string implements HasColor, HasLabel
{
    case Planning = 'planning';
    case Loading = 'loading';
    case Ready = 'ready';
    case Departed = 'departed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planning => 'Planning',
            self::Loading => 'Loading',
            self::Ready => 'Ready',
            self::Departed => 'Departed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planning => 'gray',
            self::Loading => 'blue',
            self::Ready => 'yellow',
            self::Departed => 'green',
            self::Cancelled => 'red',
        };
    }
}
