<?php

namespace App\Enums;

/** What a loader uses to secure and pack a trailer, counted per manifest. */
enum TrailerEquipment: string
{
    case Angles = 'angles';
    case Ratchets = 'ratchets';
    case Straps = 'straps';
    case Dogs = 'dogs';
    case Chains = 'chains';
    case Plywood = 'plywood';
    case RedPallets = 'red_pallets';
    case BluePallets = 'blue_pallets';

    public function label(): string
    {
        return match ($this) {
            self::RedPallets => 'Red pallets',
            self::BluePallets => 'Blue pallets',
            default => ucfirst($this->value),
        };
    }
}
