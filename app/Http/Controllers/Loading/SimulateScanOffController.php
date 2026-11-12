<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use Illuminate\Http\JsonResponse;

class SimulateScanOffController extends Controller
{
    /**
     * Pick a pallet that is loaded on this manifest, so scan-off can be tried
     * without a barcode scanner. Same gate as the other simulate endpoints.
     */
    public function __invoke(Manifest $manifest): JsonResponse
    {
        abort_unless(
            app()->environment(['local', 'testing']) || config('freight.simulate_scans'),
            404,
        );

        $pallet = HandlingUnit::query()
            ->whereHas('manifestItem', fn ($query) => $query->where('manifest_id', $manifest->getKey()))
            ->inRandomOrder()
            ->first();

        if ($pallet === null) {
            return response()->json([
                'error' => [
                    'code' => 'simulation_pallet_unavailable',
                    'message' => 'Nothing is loaded on this manifest to take off.',
                ],
            ], 404);
        }

        return response()->json(['data' => ['barcode' => $pallet->barcode]]);
    }
}
