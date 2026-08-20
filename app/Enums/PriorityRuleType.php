<?php

namespace App\Enums;

enum PriorityRuleType: string
{
    case ServiceCode = 'service_code';
    case CustomerAccount = 'customer_account';
    case WeightThreshold = 'weight_threshold';
    case DgClass = 'dg_class';
    case Manual = 'manual';
}
