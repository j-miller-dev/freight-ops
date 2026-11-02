<?php

namespace App\Enums;

enum UserRole: string implements HasColor, HasLabel
{
    case Operator = 'operator';
    case Scaler = 'scaler';
    case Supervisor = 'supervisor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator',
            self::Scaler => 'Scaler',
            self::Supervisor => 'Supervisor',
            self::Admin => 'Admin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Operator => 'gray',
            self::Scaler => 'green',
            self::Supervisor => 'blue',
            self::Admin => 'purple',
        };
    }

    /**
     * Roles that are told about things that need their attention: a loader
     * finishing a trailer, a holding lane going overdue.
     *
     * @return list<self>
     */
    public static function trailerAlertRoles(): array
    {
        return [self::Scaler, self::Supervisor, self::Admin];
    }
}
