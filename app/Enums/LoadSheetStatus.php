<?php

namespace App\Enums;

enum LoadSheetStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Loading = 'loading';
    case Complete = 'complete';
    case SignedOff = 'signed_off';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Loading => 'Loading',
            self::Complete => 'Complete',
            self::SignedOff => 'Signed Off',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Loading => 'blue',
            self::Complete => 'green',
            self::SignedOff => 'purple',
        };
    }
}
