<?php

namespace App\Enums;

enum UserRole: string
{
    case Operator = 'operator';
    case Supervisor = 'supervisor';
    case Admin = 'admin';
}
