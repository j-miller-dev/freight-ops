<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use Inertia\Inertia;
use Inertia\Response;

class ShowDepotManifestsController extends Controller
{
    /**
     * Manifest picker for one destination. The list itself is fetched
     * client-side from ListAvailableManifestsController so it can be refreshed
     * without a full page visit.
     */
    public function __invoke(Depot $depot): Response
    {
        abort_unless($depot->is_active, 404);

        return Inertia::render('loading/depot', [
            'destination' => $depot->only(['id', 'code', 'name']),
        ]);
    }
}
