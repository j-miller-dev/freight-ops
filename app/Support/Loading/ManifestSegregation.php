<?php

namespace App\Support\Loading;

use App\Calculators\SegregationEvaluator;
use App\Models\HandlingUnit;
use App\Models\LoadRule;
use App\Models\Manifest;
use App\Models\ManifestUnitLabel;
use Illuminate\Support\Facades\DB;

/**
 * What each trailer unit (A, B, C) is carrying, and which segregation rules
 * that breaks. Content comes from two places: pallets placed on the trailer
 * map, and what the loader has labelled the unit with.
 */
class ManifestSegregation
{
    /**
     * @return array{
     *     units: list<array{unit: int, name: string, categories: list<array{key: string, label: string, source: string}>}>,
     *     issues: list<array{a: string, b: string, a_label: string, b_label: string, status: string, units: list<int>, unit_names: list<string>, reason: string|null}>,
     * }
     */
    public function for(
        Manifest $manifest,
        ?HandlingUnit $adding = null,
        ?HandlingUnit $placing = null,
        ?int $placingUnit = null,
    ): array {
        $unitCount = count($manifest->trailer_type->rows());

        $present = [];
        $pinned = [];
        /** @var array<int, array<string, array<string, true>>> $sources */
        $sources = [];

        $pallets = DB::table('manifest_items')
            ->join('handling_units', 'handling_units.id', '=', 'manifest_items.handling_unit_id')
            ->where('manifest_items.manifest_id', $manifest->getKey())
            ->get(['handling_units.id', 'handling_units.dg_class', 'handling_units.is_food', 'manifest_items.trailer_unit']);

        foreach ($pallets as $pallet) {
            // "What if this pallet were placed on that unit?" overrides where it is now.
            $unit = $placing !== null && $pallet->id === $placing->getKey()
                ? $placingUnit
                : ($pallet->trailer_unit === null ? null : (int) $pallet->trailer_unit);

            foreach (LoadCategories::forPallet($pallet->dg_class, (bool) $pallet->is_food) as $category) {
                $present[] = $category;

                if ($unit !== null) {
                    $pinned[$category][] = $unit;
                    $sources[$unit][$category]['placed'] = true;
                }
            }
        }

        ManifestUnitLabel::query()
            ->where('manifest_id', $manifest->getKey())
            ->get(['unit', 'category'])
            ->each(function (ManifestUnitLabel $label) use (&$present, &$pinned, &$sources): void {
                $present[] = $label->category;
                $pinned[$label->category][] = $label->unit;
                $sources[$label->unit][$label->category]['label'] = true;
            });

        // A pallet being loaded is on the trailer but not placed on any unit yet.
        if ($adding !== null) {
            foreach (LoadCategories::forPallet($adding->dg_class, (bool) $adding->is_food) as $category) {
                $present[] = $category;
            }
        }

        $pinned = array_map(fn (array $units): array => array_values(array_unique($units)), $pinned);

        $issues = array_map(
            fn (array $issue): array => $issue + [
                'a_label' => LoadCategories::label($issue['a']),
                'b_label' => LoadCategories::label($issue['b']),
                'unit_names' => array_map(self::unitName(...), $issue['units']),
            ],
            (new SegregationEvaluator($this->rules()))->issues($present, $pinned, $unitCount),
        );

        $units = [];

        for ($unit = 1; $unit <= $unitCount; $unit++) {
            $categories = [];

            // PHP turns a numeric key like "8" into an int, so cast it back.
            foreach ($sources[$unit] ?? [] as $category => $from) {
                $category = (string) $category;

                $categories[] = [
                    'key' => $category,
                    'label' => LoadCategories::label($category),
                    'source' => count($from) > 1 ? 'both' : array_key_first($from),
                ];
            }

            $units[] = ['unit' => $unit, 'name' => self::unitName($unit), 'categories' => $categories];
        }

        return ['units' => $units, 'issues' => $issues];
    }

    /**
     * The problems that loading this pallet would add, so a loader is warned
     * about what they are about to cause and not about what is already there.
     *
     * @return list<array<string, mixed>>
     */
    public function introducedBy(Manifest $manifest, HandlingUnit $handlingUnit): array
    {
        $before = array_map($this->signature(...), $this->for($manifest)['issues']);

        return array_values(array_filter(
            $this->for($manifest, $handlingUnit)['issues'],
            fn (array $issue): bool => ! in_array($this->signature($issue), $before, true),
        ));
    }

    /**
     * The problems that placing this already-loaded pallet on a unit would
     * add, so the loader is warned about what they are about to cause.
     *
     * @return list<array<string, mixed>>
     */
    public function introducedByPlacement(Manifest $manifest, HandlingUnit $handlingUnit, int $unit): array
    {
        $before = array_map($this->signature(...), $this->for($manifest)['issues']);

        return array_values(array_filter(
            $this->for($manifest, placing: $handlingUnit, placingUnit: $unit)['issues'],
            fn (array $issue): bool => ! in_array($this->signature($issue), $before, true),
        ));
    }

    public static function unitName(int $unit): string
    {
        return chr(64 + $unit);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function signature(array $issue): string
    {
        return $issue['a'].'|'.$issue['b'].'|'.$issue['status'].'|'.implode(',', $issue['units']);
    }

    /**
     * @return list<array{a: string, b: string, reason: string|null}>
     */
    private function rules(): array
    {
        return LoadRule::query()
            ->where('is_active', true)
            ->get()
            ->map(fn (LoadRule $rule): array => [
                'a' => $rule->category_a,
                'b' => $rule->category_b,
                'reason' => $rule->reason,
            ])
            ->all();
    }
}
