<?php

namespace App\Enums;

enum TrailerType: string
{
    case Rigid = 'rigid';
    case Semi = 'semi';
    case BDouble = 'b_double';
    case BTriple = 'b_triple';
    case ADouble = 'a_double';
    case Container = 'container';
}
