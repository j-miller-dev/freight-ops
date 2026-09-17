<?php

namespace App\Enums;

enum ExceptionCategory: string implements HasColor, HasLabel
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

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Damage',
            self::MissingPiece => 'Missing Piece',
            self::UnknownFreight => 'Unknown Freight',
            self::IncorrectDestination => 'Incorrect Destination',
            self::MissingPaperwork => 'Missing Paperwork',
            self::DgDiscrepancy => 'DG Discrepancy',
            self::MisSort => 'Mis-Sort',
            self::QuantityMismatch => 'Quantity Mismatch',
            self::EquipmentIssue => 'Equipment Issue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Damage, self::MissingPiece, self::DgDiscrepancy => 'red',
            self::UnknownFreight, self::IncorrectDestination, self::QuantityMismatch => 'orange',
            self::MissingPaperwork, self::MisSort => 'yellow',
            self::EquipmentIssue => 'gray',
        };
    }
}
