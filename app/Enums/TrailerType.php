<?php

namespace App\Enums;

enum TrailerType: string implements HasLabel
{
    case Rigid = 'rigid';
    case Semi = 'semi';
    case BDouble = 'b_double';
    case BTriple = 'b_triple';
    case ADouble = 'a_double';
    case Container = 'container';

    /**
     * The trailer types a loader can choose between.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::BDouble, self::BTriple, self::ADouble];
    }

    public function label(): string
    {
        return match ($this) {
            self::Rigid => 'Rigid',
            self::Semi => 'Semi',
            self::BDouble => 'B-Double',
            self::BTriple => 'B-Triple',
            self::ADouble => 'A-Double',
            self::Container => 'Container',
        };
    }

    /**
     * Pallet rows on each trailer unit, front to back. Every row holds two
     * pallets, one each side.
     *
     * @return list<int>
     */
    public function rows(): array
    {
        return match ($this) {
            self::BDouble => [7, 10],
            // Placeholders until the real layouts are confirmed.
            self::BTriple => [7, 7, 8],
            self::ADouble => [7, 7],
            self::Semi, self::Container => [11],
            self::Rigid => [8],
        };
    }

    /** Whether the layout comes from the floor rather than a placeholder. */
    public function isLayoutConfirmed(): bool
    {
        return $this === self::BDouble;
    }

    public function capacity(): int
    {
        return array_sum($this->rows()) * 2;
    }
}
