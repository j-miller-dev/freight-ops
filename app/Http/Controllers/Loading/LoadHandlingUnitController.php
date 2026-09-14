<?php

namespace App\Http\Controllers\Loading;

use App\Actions\Loading\LoadHandlingUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loading\LoadHandlingUnitRequest;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class LoadHandlingUnitController extends Controller
{
    public function __invoke(
        LoadHandlingUnitRequest $request,
        Manifest $manifest,
        LoadHandlingUnit $action,
    ): JsonResponse {
        $handlingUnit = HandlingUnit::query()
            ->where('barcode', $request->validated('barcode'))
            ->firstOrFail();

        $manifestItem = $action->handle(
            manifest: $manifest,
            handlingUnit: $handlingUnit,
            loader: $request->user(),
            clientEventId: $request->validated('client_event_id'),
            occurredAt: CarbonImmutable::parse($request->validated('occurred_at')),
            acknowledgedWarnings: $request->acknowledgedWarnings(),
        );

        return response()->json([
            'data' => [
                'manifest_item_id' => $manifestItem->getKey(),
                'manifest_id' => $manifestItem->manifest_id,
                'handling_unit_id' => $manifestItem->handling_unit_id,
                'barcode' => $handlingUnit->barcode,
                'loaded_at' => $manifestItem->loaded_at?->toISOString(),
            ],
        ], 201);
    }
}
