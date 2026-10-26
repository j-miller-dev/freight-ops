<?php

namespace App\Http\Controllers\Unload;

use App\Actions\Unload\MoveHandlingUnit;
use App\Exceptions\Unload\DirectionChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unload\ConfirmHandlingUnitDropRequest;
use App\Models\HandlingUnit;
use App\Support\Unload\BayDirector;
use App\Support\Unload\DirectionPayload;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class ConfirmHandlingUnitDropController extends Controller
{
    public function __invoke(
        ConfirmHandlingUnitDropRequest $request,
        BayDirector $director,
        MoveHandlingUnit $action,
    ): JsonResponse {
        $handlingUnit = HandlingUnit::query()
            ->where('barcode', $request->validated('barcode'))
            ->with(['consignment', 'currentLocation'])
            ->firstOrFail();

        $direction = $director->direct($handlingUnit);

        if ($direction->location->getKey() !== $request->validated('location_id')) {
            // A held pallet may go in another lane when the suggested one is full.
            // A confirm that waited in the offline queue is recorded where the
            // runner put the pallet. Anything else means the rules moved on:
            // show the new bay first.
            $direction = $director->withLaneOverride($direction, $request->validated('location_id'))
                ?? ($request->boolean('queued')
                    ? $director->physicalDrop($direction, $request->validated('location_id'))
                    : null)
                ?? throw new DirectionChanged($direction);
        }

        $moved = $action->handle(
            handlingUnit: $handlingUnit,
            direction: $direction,
            runner: $request->user(),
            clientEventId: $request->validated('client_event_id'),
            occurredAt: CarbonImmutable::parse($request->validated('occurred_at')),
        );

        return response()->json([
            'data' => DirectionPayload::for($moved->load('consignment'), $direction),
        ]);
    }
}
