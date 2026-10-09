<?php

namespace App\Http\Controllers\Loading;

use App\Actions\Loading\SetConsignmentHoldBack;
use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\Manifest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateConsignmentHoldBackController extends Controller
{
    public function __invoke(
        Request $request,
        Manifest $manifest,
        Consignment $consignment,
        SetConsignmentHoldBack $action,
    ): JsonResponse {
        Gate::authorize('holdBack', $consignment);

        $validated = $request->validate([
            'held_back' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $consignment = $action->handle(
            consignment: $consignment,
            actor: $request->user(),
            heldBack: $validated['held_back'],
            reason: $validated['reason'] ?? null,
            occurredAt: CarbonImmutable::now(),
        );

        return response()->json([
            'data' => [
                'held_back' => $consignment->isHeldBack(),
                'held_back_reason' => $consignment->held_back_reason,
                'held_back_by' => $consignment->heldBackBy?->name,
                'held_back_at' => $consignment->held_back_at?->toISOString(),
            ],
        ]);
    }
}
