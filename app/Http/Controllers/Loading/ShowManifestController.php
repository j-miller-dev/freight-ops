<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowManifestController extends Controller
{
    public function __invoke(Request $request, Manifest $manifest): Response
    {
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
                'status',
                'manifest_items_count',
            ]),
            'destination' => $destination?->only(['id', 'code', 'name']),
        ]);
    }
}
