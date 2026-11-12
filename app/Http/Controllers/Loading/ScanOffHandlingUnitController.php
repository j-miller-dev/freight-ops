<?php

namespace App\Http\Controllers\Loading;

use App\Actions\Loading\RemoveHandlingUnitFromManifest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loading\ScanOffHandlingUnitRequest;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScanOffHandlingUnitController extends Controller
{
    public function __invoke(
        ScanOffHandlingUnitRequest $request,
        Manifest $manifest,
        RemoveHandlingUnitFromManifest $action,
    ): JsonResponse {
        Gate::authorize('load', $manifest);

        $handlingUnit = HandlingUnit::query()
            ->where('barcode', $request->validated('barcode'))
            ->with('consignment')
            ->firstOrFail();

        $handlingUnit = $action->handle(
            manifest: $manifest,
            handlingUnit: $handlingUnit,
            actor: $request->user(),
            clientEventId: $request->validated('client_event_id'),
            occurredAt: CarbonImmutable::parse($request->validated('occurred_at')),
        );

        $destinationIds = $manifest->destinations()->pluck('depots.id');

        return response()->json([
            'data' => [
                'handling_unit_id' => $handlingUnit->getKey(),
                'barcode' => $handlingUnit->barcode,
                'piece_number' => $handlingUnit->piece_number,
                'connote_number' => $handlingUnit->consignment->connote_number,
                'status' => $handlingUnit->current_status->value,
                'location' => $handlingUnit->currentLocation?->only(['id', 'code', 'name']),
                'progress' => [
                    'loaded_count' => $manifest->manifestItems()->count(),
                    'total_count' => HandlingUnit::query()
                        ->whereHas(
                            'consignment',
                            fn ($query) => $query->whereIn('destination_depot_id', $destinationIds),
                        )
                        ->count(),
                ],
                'consignment_progress' => [
                    'loaded_count' => $handlingUnit->consignment->loadedCount(),
                    'total_count' => $handlingUnit->consignment->item_count,
                ],
            ],
        ]);
    }
}
