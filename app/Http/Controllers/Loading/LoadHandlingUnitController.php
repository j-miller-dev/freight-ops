<?php

namespace App\Http\Controllers\Loading;

use App\Actions\Loading\LoadHandlingUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loading\LoadHandlingUnitRequest;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LoadHandlingUnitController extends Controller
{
    public function __invoke(
        LoadHandlingUnitRequest $request,
        Manifest $manifest,
        LoadHandlingUnit $action,
    ): JsonResponse {
        Gate::authorize('load', $manifest);

        $handlingUnit = HandlingUnit::query()
            ->where('barcode', $request->validated('barcode'))
            ->with('consignment')
            ->firstOrFail();

        $manifestItem = $action->handle(
            manifest: $manifest,
            handlingUnit: $handlingUnit,
            loader: $request->user(),
            clientEventId: $request->validated('client_event_id'),
            occurredAt: CarbonImmutable::parse($request->validated('occurred_at')),
            acknowledgedWarnings: $request->acknowledgedWarnings(),
        );

        $destinationIds = $manifest->destinations()->pluck('depots.id');
        $loadedCount = $manifest->manifestItems()->count();
        $totalCount = HandlingUnit::query()
            ->whereHas(
                'consignment',
                fn ($query) => $query->whereIn('destination_depot_id', $destinationIds),
            )
            ->count();

        return response()->json([
            'data' => [
                'manifest_item_id' => $manifestItem->getKey(),
                'manifest_id' => $manifestItem->manifest_id,
                'handling_unit_id' => $manifestItem->handling_unit_id,
                'barcode' => $handlingUnit->barcode,
                'piece_number' => $handlingUnit->piece_number,
                'connote_number' => $handlingUnit->consignment->connote_number,
                'loaded_at' => $manifestItem->loaded_at?->toISOString(),
                'loader' => [
                    'id' => $request->user()->getKey(),
                    'name' => $request->user()->name,
                ],
                'progress' => [
                    'loaded_count' => $loadedCount,
                    'total_count' => $totalCount,
                ],
                'consignment_progress' => [
                    'loaded_count' => $handlingUnit->consignment->loadedCount(),
                    'total_count' => $handlingUnit->consignment->item_count,
                ],
            ],
        ], 201);
    }
}
