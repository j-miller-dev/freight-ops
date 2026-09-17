<?php

namespace App\Enums;

enum PackingGroup: string implements HasColor, HasLabel
{
    case I = 'I';
    case II = 'II';
    case III = 'III';

    public function label(): string
    {
        return match ($this) {
            self::I => 'Packing Group I (High Danger)',
            self::II => 'Packing Group II (Medium Danger)',
            self::III => 'Packing Group III (Low Danger)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::I => 'red',
            self::II => 'orange',
            self::III => 'yellow',
        };
    }
}
