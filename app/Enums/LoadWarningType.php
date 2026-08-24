<?php

namespace App\Enums;

enum LoadWarningType: string
{
    case DestinationMismatch = 'destination_mismatch';
    case ConsignmentSplit = 'consignment_split';
}
