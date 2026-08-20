<?php

namespace App\Enums;

enum DgCompatibilityRule: string
{
    case Compatible = 'compatible';
    case Separated = 'separated';
    case Segregated = 'segregated';
    case Incompatible = 'incompatible';
}
