<?php

namespace App\Enums;

enum NotificationSeverity: string implements HasColor, HasLabel
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'blue',
            self::Warning => 'yellow',
            self::Critical => 'red',
        };
    }
}
