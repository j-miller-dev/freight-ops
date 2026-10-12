<?php

namespace App\Http\Controllers\Unload;

use App\Enums\HandlingUnitStatus;
use App\Http\Controllers\Controller;
use App\Models\HandlingUnit;
use Illuminate\Http\JsonResponse;

class SimulateUnloadScanController extends Controller
{
    /**
     * Pick a seeded pallet that has not been placed yet, so the runner flow can
     * be tried without a barcode scanner.
     */
    public function __invoke(): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $pallet = HandlingUnit::query()
            ->where('current_status', '!=', HandlingUnitStatus::Loaded->value)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('manifest_items')
                    ->whereColumn('manifest_items.handling_unit_id', 'handling_units.id');
            })
            // Unplaced pallets first; once they run out, any unloaded pallet.
            ->orderByRaw('current_location_id is null desc')
            ->inRandomOrder()
            ->first();

        if ($pallet === null) {
            return response()->json([
                'error' => [
                    'code' => 'simulation_pallet_unavailable',
                    'message' => 'No seeded pallet is available to unload.',
                ],
            ], 404);
        }

        return response()->json(['data' => ['barcode' => $pallet->barcode]]);
    }
}
