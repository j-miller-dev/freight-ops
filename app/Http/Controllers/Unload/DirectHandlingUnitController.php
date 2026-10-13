<?php

namespace App\Http\Controllers\Unload;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unload\DirectHandlingUnitRequest;
use App\Models\HandlingUnit;
use App\Support\Unload\BayDirector;
use App\Support\Unload\DirectionPayload;
use Illuminate\Http\JsonResponse;

class DirectHandlingUnitController extends Controller
{
    /**
     * Tell the runner which bay a scanned pallet goes to. Nothing is recorded
     * until they confirm the drop-off.
     */
    public function __invoke(DirectHandlingUnitRequest $request, BayDirector $director): JsonResponse
    {
        $handlingUnit = HandlingUnit::query()
            ->where('barcode', $request->validated('barcode'))
            ->with(['consignment', 'currentLocation'])
            ->firstOrFail();

        $direction = $director->direct($handlingUnit);

        return response()->json([
            'data' => DirectionPayload::for(
                $handlingUnit,
                $direction,
                $direction->isHolding ? $director->holdingLanes() : null,
            ),
        ]);
    }
}
