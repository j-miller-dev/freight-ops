<?php

namespace App\Http\Controllers\Unload;

use App\Http\Controllers\Controller;
use App\Support\Unload\HoldingLaneBoard;
use Illuminate\Http\JsonResponse;

class ListHoldingLanesController extends Controller
{
    public function __invoke(HoldingLaneBoard $board): JsonResponse
    {
        return response()->json(['data' => $board->lanes()]);
    }
}
