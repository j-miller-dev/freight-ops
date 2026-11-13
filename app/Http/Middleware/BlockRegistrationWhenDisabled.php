<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public sign-up is closed unless FORTIFY_REGISTRATION=true. The /register
 * route is still defined (the front end build generates its route helpers
 * from the route list), so the refusal happens here instead.
 */
class BlockRegistrationWhenDisabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->is('register') && ! config('fortify.allow_registration'),
            404,
        );

        return $next($request);
    }
}
