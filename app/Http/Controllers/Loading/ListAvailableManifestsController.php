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
            ->whereDate('service_date', '>=', today()->subDay())
            ->whereDate('service_date', '<=', today())
            ->whereHas(
                'destinations',
                fn ($query) => $query->whereKey($destination->getKey()),
            )
            ->withCount('manifestItems')
            ->orderBy('service_date')
            ->orderBy('manifest_number')
            ->get()
            ->sortBy(fn (Manifest $manifest): array => [
                $manifest->status === 'open' ? 0 : 1,
                $manifest->service_date?->toDateString(),
                $manifest->manifest_number,
            ])
            ->values();

        return response()->json([
            'data' => $manifests,
        ]);
    }
}
