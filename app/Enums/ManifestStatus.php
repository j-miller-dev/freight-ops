<?php

namespace App\Enums;

enum ManifestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Receiving = 'receiving';
    case Complete = 'complete';
    case Dispatched = 'dispatched';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Receiving => 'Receiving',
            self::Complete => 'Complete',
            self::Dispatched => 'Dispatched',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Receiving => 'blue',
            self::Complete => 'green',
            self::Dispatched => 'purple',
        };
    }
}
