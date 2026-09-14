<?php

namespace App\Http\Controllers\Loading;

use App\Enums\HandlingUnitStatus;
use App\Http\Controllers\Controller;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use Illuminate\Http\JsonResponse;

class SimulateHandlingUnitScanController extends Controller
{
    public function __invoke(Manifest $manifest): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $destinationIds = $manifest->destinations()->pluck('depots.id');

        $pallet = HandlingUnit::query()
            ->with('consignment')
            ->where('current_status', HandlingUnitStatus::Pending->value)
            ->whereHas(
                'consignment',
                fn ($query) => $query->whereIn('destination_depot_id', $destinationIds),
            )
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('manifest_items')
                    ->whereColumn('manifest_items.handling_unit_id', 'handling_units.id');
            })
            ->inRandomOrder()
            ->first();

        if ($pallet === null) {
            return response()->json([
                'error' => [
                    'code' => 'simulation_pallet_unavailable',
                    'message' => 'No unassigned pending seeded pallet is available for this manifest.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'barcode' => $pallet->barcode,
                'connote_number' => $pallet->consignment->connote_number,
                'piece_number' => $pallet->piece_number,
                'destination_depot_id' => $pallet->consignment->destination_depot_id,
            ],
        ]);
    }
}
