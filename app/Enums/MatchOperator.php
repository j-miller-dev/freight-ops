<?php

namespace App\Enums;

enum MatchOperator: string
{
    case Equals = 'equals';
    case Contains = 'contains';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case InList = 'in_list';
}
