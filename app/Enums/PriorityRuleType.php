<?php

namespace App\Enums;

enum PriorityRuleType: string implements HasLabel
{
    case ServiceCode = 'service_code';
    case CustomerAccount = 'customer_account';
    case WeightThreshold = 'weight_threshold';
    case DgClass = 'dg_class';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::ServiceCode => 'Service Code',
            self::CustomerAccount => 'Customer Account',
            self::WeightThreshold => 'Weight Threshold',
            self::DgClass => 'DG Class',
            self::Manual => 'Manual',
        };
    }
}
