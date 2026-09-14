<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Models\Manifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListAvailableManifestsController extends Controller
{
    /**
     * Only responsible for filtering manifests after destination is selected.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination_id' => ['required', 'uuid', 'exists:depots,id'],
        ]);

        $destination = Depot::query()->findOrFail($validated['destination_id']);

        $manifests = Manifest::query()
            ->availableForLoading($destination, today()->subDay(), today())
            ->withCount('manifestItems')
            ->orderBy('service_date')
            ->orderBy('manifest_number')
            ->get();

        return response()->json([
            'data' => $manifests,
        ]);
    }
}
