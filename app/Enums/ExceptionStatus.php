<?php

namespace App\Enums;

enum ExceptionStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Investigating => 'Investigating',
            self::Resolved => 'Resolved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'red',
            self::Investigating => 'yellow',
            self::Resolved => 'green',
        };
    }
}
