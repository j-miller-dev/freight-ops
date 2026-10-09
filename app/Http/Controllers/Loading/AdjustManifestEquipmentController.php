<?php

namespace App\Http\Controllers\Loading;

use App\Enums\TrailerEquipment;
use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\ManifestEquipment;
use App\Support\Loading\ManifestEquipmentCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjustManifestEquipmentController extends Controller
{
    /**
     * Add to or take from one equipment count. A relative change, applied in the
     * database, so two loaders tapping at once never overwrite each other.
     */
    public function __invoke(Request $request, Manifest $manifest, ManifestEquipmentCounts $counts): JsonResponse
    {
        Gate::authorize('load', $manifest);

        $validated = $request->validate([
            'item' => ['required', Rule::enum(TrailerEquipment::class)],
            'delta' => ['required', 'integer', 'between:-50,50', 'not_in:0'],
        ]);

        ManifestEquipment::query()->firstOrCreate(
            ['manifest_id' => $manifest->getKey(), 'item' => $validated['item']],
            ['quantity' => 0],
        );

        $row = ManifestEquipment::query()
            ->where('manifest_id', $manifest->getKey())
            ->where('item', $validated['item']);

        $delta = (int) $validated['delta'];

        if ($delta > 0) {
            $row->increment('quantity', $delta);
        } else {
            // Take away only what is there, then floor at zero.
            (clone $row)->where('quantity', '>=', -$delta)->decrement('quantity', -$delta);
            (clone $row)->where('quantity', '<', -$delta)->update(['quantity' => 0]);
        }

        return response()->json(['data' => $counts->for($manifest)]);
    }
}
