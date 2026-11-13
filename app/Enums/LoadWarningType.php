<?php

namespace App\Enums;

enum LoadWarningType: string
{
    case DestinationMismatch = 'destination_mismatch';
    case ConsignmentSplit = 'consignment_split';
    case AlreadyAssigned = 'already_assigned';
    // Superseded by SegregationClash; kept so old acknowledgements still read.
    case FoodDgClash = 'food_dg_clash';
    case SegregationClash = 'segregation_clash';
}
