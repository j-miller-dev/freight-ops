<?php

namespace App\Enums;

enum MatchOperator: string implements HasLabel
{
    case Equals = 'equals';
    case Contains = 'contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case InList = 'in_list';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'Equals',
            self::Contains => 'Contains',
            self::GreaterThan => 'Greater Than',
            self::LessThan => 'Less Than',
            self::InList => 'In List',
        };
    }
}
