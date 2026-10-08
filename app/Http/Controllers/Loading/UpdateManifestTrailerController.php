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

        $manifest->trailer_type = TrailerType::from($validated['trailer_type']);
        $manifest->save();

        return response()->json([
            'data' => ['trailer_type' => $manifest->trailer_type->value],
        ]);
    }
}
