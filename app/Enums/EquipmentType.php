<?php

namespace App\Enums;

enum EquipmentType: string implements HasLabel
{
    case Forklift = 'forklift';
    case PalletJack = 'pallet_jack';
    case ReachTruck = 'reach_truck';
    case DockLeveller = 'dock_leveller';

    public function label(): string
    {
        return match ($this) {
            self::Forklift => 'Forklift',
            self::PalletJack => 'Pallet Jack',
            self::ReachTruck => 'Reach Truck',
            self::DockLeveller => 'Dock Leveller',
        };
    }
}
