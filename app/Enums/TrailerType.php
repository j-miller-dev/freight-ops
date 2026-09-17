<?php

namespace App\Enums;

enum TrailerType: string implements HasLabel
{
    case Rigid = 'rigid';
    case Semi = 'semi';
    case BDouble = 'b_double';
    case Container = 'container';

    public function label(): string
    {
        return match ($this) {
            self::Rigid => 'Rigid',
            self::Semi => 'Semi',
            self::BDouble => 'B-Double',
            self::Container => 'Container',
        };
    }
}
