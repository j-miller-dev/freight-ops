<?php

namespace App\Http\Controllers\Loading;

use App\Actions\Loading\CloseManifest;
use App\Http\Controllers\Controller;
use App\Models\Manifest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CloseManifestController extends Controller
{
    public function __invoke(Request $request, Manifest $manifest, CloseManifest $action): JsonResponse
    {
        Gate::authorize('close', $manifest);

        $validated = $request->validate([
            'acknowledged' => ['sometimes', 'boolean'],
        ]);

        $closed = $action->handle(
            manifest: $manifest,
            closedBy: $request->user(),
            acknowledged: (bool) ($validated['acknowledged'] ?? false),
        );

        return response()->json([
            'data' => [
                'status' => $closed->status,
                'closed_at' => $closed->closed_at?->toISOString(),
                'closed_by' => $closed->closedBy?->name,
            ],
        ]);
    }
}
