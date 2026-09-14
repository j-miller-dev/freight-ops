<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use Inertia\Inertia;
use Inertia\Response;

class LoadingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('loading/index', [
            'destinations' => Depot::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }
}
