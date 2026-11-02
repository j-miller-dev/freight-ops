<?php

namespace App\Http\Controllers\Unload;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Support\Unload\HoldingLaneBoard;
use Illuminate\Http\JsonResponse;

class ShowHoldingLaneController extends Controller
{
    public function __invoke(Location $location, HoldingLaneBoard $board): JsonResponse
    {
        return response()->json(['data' => $board->detail($location->getKey())]);
    }
}
