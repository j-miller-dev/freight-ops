<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Depot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoadingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('loading/index', [
            'loader' => $request->user()->only(['id', 'name']),
            'destinations' => Depot::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }
}
