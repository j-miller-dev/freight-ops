<?php

namespace App\Http\Controllers\Unload;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Support\Unload\BayConsignmentBoard;
use Illuminate\Http\JsonResponse;

class ShowBayBoardController extends Controller
{
    /** How much of this consignment, and its bay-mates, is in the bay so far. */
    public function __invoke(Consignment $consignment, BayConsignmentBoard $board): JsonResponse
    {
        return response()->json(['data' => $board->for($consignment)]);
    }
}
