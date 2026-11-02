<?php

namespace App\Http\Controllers\Unload;

use App\Actions\Unload\ReleaseHandlingUnit;
use App\Http\Controllers\Controller;
use App\Models\HandlingUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReleaseHandlingUnitController extends Controller
{
    public function __invoke(Request $request, HandlingUnit $handlingUnit, ReleaseHandlingUnit $action): JsonResponse
    {
        Gate::authorize('release', $handlingUnit);

        $released = $action->handle(
            handlingUnit: $handlingUnit,
            releasedBy: $request->user(),
            occurredAt: now(),
        );

        return response()->json([
            'data' => [
                'id' => $released->getKey(),
                'released_from_hold_at' => $released->released_from_hold_at?->toISOString(),
                'released_from_hold_by' => $released->releasedFromHoldBy?->name,
            ],
        ]);
    }
}
