<?php

namespace App\Enums;

enum EquipmentType: string
{
    case Forklift = 'forklift';
    case PalletJack = 'pallet_jack';
    case ReachTruck = 'reach_truck';
    case DockLeveller = 'dock_leveller';
}
