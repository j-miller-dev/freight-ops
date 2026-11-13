<?php

namespace App\Support\Loading;

/**
 * The kinds of freight the segregation rules talk about: each DG class, plus
 * a few that are not a DG class (food, oil) or are derived from one (flammable).
 */
class LoadCategories
{
    public const FOOD = 'food';

    public const OIL = 'oil';

    public const FLAMMABLE = 'flammable';

    /** DG classes a loader can name on a trailer unit. */
    public const DG_CLASSES = [
        '1', '2.1', '2.2', '2.3', '3', '4.1', '4.2', '4.3',
        '5.1', '5.2', '6.1', '6.2', '7', '8', '9',
    ];

    /** DG classes that count as "flammable" for the rules. */
    private const FLAMMABLE_CLASSES = ['2.1', '3'];

    /**
     * Every category a pallet belongs to, e.g. class 3 is also flammable.
     *
     * @return list<string>
     */
    public static function forPallet(?string $dgClass, bool $isFood): array
    {
        $categories = [];

        if ($dgClass !== null && $dgClass !== '') {
            $categories[] = $dgClass;

            if (in_array($dgClass, self::FLAMMABLE_CLASSES, true)) {
                $categories[] = self::FLAMMABLE;
            }
        }

        if ($isFood) {
            $categories[] = self::FOOD;
        }

        return $categories;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::options(), 'key');
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::FOOD => 'Food',
            self::OIL => 'Oil',
            self::FLAMMABLE => 'Flammable',
            default => "Class {$key}",
        };
    }

    /**
     * What the loader can pick from when labelling a unit.
     *
     * @return list<array{key: string, label: string, kind: string}>
     */
    public static function options(): array
    {
        $other = array_map(
            fn (string $key): array => ['key' => $key, 'label' => self::label($key), 'kind' => 'other'],
            [self::FOOD, self::OIL, self::FLAMMABLE],
        );

        $dg = array_map(
            fn (string $key): array => ['key' => $key, 'label' => self::label($key), 'kind' => 'dg'],
            self::DG_CLASSES,
        );

        return [...$other, ...$dg];
    }
}
