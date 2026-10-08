<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Support\Loading\ManifestConsignmentBoard;
use App\Support\Loading\ManifestSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowManifestController extends Controller
{
    public function __invoke(
        Request $request,
        Manifest $manifest,
        ManifestSummary $summary,
        ManifestConsignmentBoard $board,
    ): Response {
        $manifest->loadCount('manifestItems');

        $destination = $manifest->destinations()
            ->orderByDesc('manifest_destinations.is_primary')
            ->first(['depots.id', 'depots.code', 'depots.name']);

        return Inertia::render('loading/manifest', [
            'loader' => $request->user()->only(['id', 'name']),
            'manifest' => $manifest->only([
                'id',
                'manifest_number',
                'service_date',
                'departs_at',
                'status',
                'manifest_items_count',
            ]) + ['trailer_type' => $manifest->trailer_type->value],
            'summary' => $summary->for($manifest),
            'bay_code' => $board->bayFor($manifest)?->code,
            'destination' => $destination?->only(['id', 'code', 'name']),
        ]);
    }
}
