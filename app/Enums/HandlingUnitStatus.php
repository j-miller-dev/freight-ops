<?php

namespace App\Enums;

enum HandlingUnitStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Received = 'received';
    case Staged = 'staged';
    case Loaded = 'loaded';
    case Dispatched = 'dispatched';
    case Exception = 'exception';
    case Held = 'held';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Received => 'Received',
            self::Staged => 'Staged',
            self::Loaded => 'Loaded',
            self::Dispatched => 'Dispatched',
            self::Exception => 'Exception',
            self::Held => 'Held',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Received => 'blue',
            self::Staged => 'indigo',
            self::Loaded => 'yellow',
            self::Dispatched => 'purple',
            self::Exception => 'red',
            self::Held => 'orange',
        };
    }
}
