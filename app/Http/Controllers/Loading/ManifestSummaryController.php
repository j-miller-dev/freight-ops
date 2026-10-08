<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Support\Loading\ManifestSummary;
use Illuminate\Http\JsonResponse;

class ManifestSummaryController extends Controller
{
    public function __invoke(Manifest $manifest, ManifestSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->for($manifest)]);
    }
}
