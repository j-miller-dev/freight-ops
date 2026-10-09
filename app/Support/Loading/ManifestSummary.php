<?php

namespace App\Support\Loading;

use App\Calculators\DgSegregationChecker;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use Illuminate\Support\Facades\DB;

/** What a loader needs to see at a glance about the freight already on a trailer. */
class ManifestSummary
{
    public function __construct(private readonly DgSegregationChecker $checker) {}

    /**
     * @return array{
     *     loaded_count: int,
     *     consignments_total: int,
     *     consignments_complete: int,
     *     dg: list<array{class: string, count: int}>,
     *     dg_items: list<array<string, mixed>>,
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

        $consignments = DB::table('manifest_items')
            ->join('handling_units', 'handling_units.id', '=', 'manifest_items.handling_unit_id')
            ->join('consignments', 'consignments.id', '=', 'handling_units.consignment_id')
            ->where('manifest_items.manifest_id', $manifest->getKey())
            ->groupBy('consignments.id', 'consignments.item_count')
            ->selectRaw('consignments.item_count as item_count, count(*) as on_trailer')
            ->get();

        $dgItems = array_values(DB::table('manifest_items')
            ->join('handling_units', 'handling_units.id', '=', 'manifest_items.handling_unit_id')
            ->join('consignments', 'consignments.id', '=', 'handling_units.consignment_id')
            ->where('manifest_items.manifest_id', $manifest->getKey())
            ->whereNotNull('handling_units.dg_class')
            ->orderBy('handling_units.dg_class')
            ->orderBy('handling_units.barcode')
            ->get([
                'handling_units.id',
                'handling_units.barcode',
                'handling_units.piece_number',
                'handling_units.dg_class',
                'handling_units.un_number',
                'handling_units.proper_shipping_name',
                'consignments.connote_number',
                'manifest_items.trailer_unit',
                'manifest_items.trailer_row',
                'manifest_items.trailer_side',
            ])
            ->map(fn (object $row): array => [
                'id' => $row->id,
                'barcode' => $row->barcode,
                'piece_number' => (int) $row->piece_number,
                'dg_class' => $row->dg_class,
                'un_number' => $row->un_number,
                'proper_shipping_name' => $row->proper_shipping_name,
                'connote_number' => $row->connote_number,
                'position' => $row->trailer_unit === null ? null : [
                    'unit' => (int) $row->trailer_unit,
                    'row' => (int) $row->trailer_row,
                    'side' => $row->trailer_side,
                ],
            ])
            ->all());

        return [
            'loaded_count' => $manifest->manifestItems()->count(),
            'consignments_total' => $consignments->count(),
            'consignments_complete' => $consignments
                ->filter(fn (object $row): bool => (int) $row->on_trailer >= (int) $row->item_count)
                ->count(),
            'dg_items' => $dgItems,
            'dg' => $dg,
            'food_count' => $foodCount,
            'food_conflicts' => $this->checker->foodConflicts(
                array_column($dg, 'class'),
                $foodCount > 0,
            ),
        ];
    }
}
