<?php

namespace App\Support\Loading;

use App\Calculators\DgSegregationChecker;
use App\Models\HandlingUnit;
use App\Models\Manifest;

/** What a loader needs to see at a glance about the freight already on a trailer. */
class ManifestSummary
{
    public function __construct(private readonly DgSegregationChecker $checker) {}

    /**
     * @return array{
     *     loaded_count: int,
     *     dg: list<array{class: string, count: int}>,
     *     food_count: int,
     *     food_conflicts: list<string>,
     * }
     */
    public function for(Manifest $manifest): array
    {
        $onTrailer = fn () => HandlingUnit::query()
            ->join('manifest_items', 'manifest_items.handling_unit_id', '=', 'handling_units.id')
            ->where('manifest_items.manifest_id', $manifest->getKey());

        /** @var list<array{class: string, count: int}> $dg */
        $dg = $onTrailer()
            ->whereNotNull('handling_units.dg_class')
            ->selectRaw('handling_units.dg_class as class, count(*) as count')
            ->groupBy('handling_units.dg_class')
            ->orderBy('handling_units.dg_class')
            ->get()
            ->map(fn ($row): array => [
                'class' => (string) $row->getAttribute('class'),
                'count' => (int) $row->getAttribute('count'),
            ])
            ->all();

        $foodCount = $onTrailer()->where('handling_units.is_food', true)->count();

        return [
            'loaded_count' => $manifest->manifestItems()->count(),
            'dg' => $dg,
            'food_count' => $foodCount,
            'food_conflicts' => $this->checker->foodConflicts(
                array_column($dg, 'class'),
                $foodCount > 0,
            ),
        ];
    }
}
