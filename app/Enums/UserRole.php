<?php

namespace App\Enums;

enum UserRole: string
{
    case Driver = 'driver';
    case Unloader = 'unloader';
    case Loader = 'loader';
    case Supervisor = 'supervisor';
    case OpsManager = 'ops_manager';
    case Admin = 'admin';
}
