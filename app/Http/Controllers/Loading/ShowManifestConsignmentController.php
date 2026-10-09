<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\Manifest;
use App\Support\Loading\ManifestConsignmentBoard;
use Illuminate\Http\JsonResponse;

class ShowManifestConsignmentController extends Controller
{
    public function __invoke(
        Manifest $manifest,
        Consignment $consignment,
        ManifestConsignmentBoard $board,
    ): JsonResponse {
        return response()->json(['data' => $board->detail($manifest, $consignment)]);
    }
}
