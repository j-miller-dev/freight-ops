<?php

namespace App\Http\Controllers\Unload;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UnloadController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('unload/index');
    }
}
