<?php

namespace App\Enums;

enum EquipmentStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::InUse => 'In Use',
            self::Maintenance => 'Maintenance',
            self::Decommissioned => 'Decommissioned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'green',
            self::InUse => 'blue',
            self::Maintenance => 'yellow',
            self::Decommissioned => 'gray',
        };
    }
}
