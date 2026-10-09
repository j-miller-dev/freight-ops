<?php

namespace App\Http\Controllers\Loading;

use App\Enums\TrailerType;
use App\Http\Controllers\Controller;
use App\Models\Manifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateManifestTrailerController extends Controller
{
    public function __invoke(Request $request, Manifest $manifest): JsonResponse
    {
        Gate::authorize('load', $manifest);

        $validated = $request->validate([
            'trailer_type' => ['required', Rule::enum(TrailerType::class)],
        ]);

        $type = TrailerType::from($validated['trailer_type']);
        $cleared = 0;

        if ($type !== $manifest->trailer_type) {
            // Positions belong to the old layout, so they no longer mean anything.
            $cleared = $manifest->manifestItems()
                ->whereNotNull('trailer_unit')
                ->update(['trailer_unit' => null, 'trailer_row' => null, 'trailer_side' => null]);
        }

        $manifest->trailer_type = $type;
        $manifest->save();

        return response()->json([
            'data' => ['trailer_type' => $type->value, 'positions_cleared' => $cleared],
        ]);
    }
}
