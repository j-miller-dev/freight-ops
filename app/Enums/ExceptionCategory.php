<?php

namespace App\Enums;

enum ExceptionCategory: string
{
    case Damage = 'damage';
    case MissingPiece = 'missing_piece';
    case UnknownFreight = 'unknown_freight';
    case IncorrectDestination = 'incorrect_destination';
    case MissingPaperwork = 'missing_paperwork';
    case DgDiscrepancy = 'dg_discrepancy';
    case MisSort = 'mis_sort';
    case QuantityMismatch = 'quantity_mismatch';
    case EquipmentIssue = 'equipment_issue';
}
