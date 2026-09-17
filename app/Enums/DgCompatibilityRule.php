<?php

namespace App\Enums;

enum DgCompatibilityRule: string implements HasColor, HasLabel
{
    case Compatible = 'compatible';
    case Separated = 'separated';
    case Segregated = 'segregated';
    case Incompatible = 'incompatible';

    public function label(): string
    {
        return match ($this) {
            self::Compatible => 'Compatible',
            self::Separated => 'Separated',
            self::Segregated => 'Segregated',
            self::Incompatible => 'Incompatible',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Compatible => 'green',
            self::Separated => 'yellow',
            self::Segregated => 'orange',
            self::Incompatible => 'red',
        };
    }
}
