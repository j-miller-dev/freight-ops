<?php

namespace App\Enums;

enum UserRole: string implements HasColor, HasLabel
{
    case Operator = 'operator';
    case Supervisor = 'supervisor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::Supervisor => 'Supervisor',
            self::Admin => 'Admin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Operator => 'gray',
            self::Supervisor => 'blue',
            self::Admin => 'purple',
        };
    }
}
