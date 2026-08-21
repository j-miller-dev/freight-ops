<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Available = 'available';
    case InUse = 'in_use';
    case Maintenance = 'maintenance';
    case Decommissioned = 'decommissioned';
}
