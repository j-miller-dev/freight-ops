<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** There is no public landing page: send people to where they work. */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route($request->user() ? 'dashboard' : 'login');
    }
}
