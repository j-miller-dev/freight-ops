<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PinLoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __invoke(Request $request): RedirectResponse|JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
        ], [
            'pin.regex' => 'Enter your 4 to 6 digit PIN.',
        ]);

        $username = Str::lower(trim($credentials['username']));
        $throttleKey = Str::transliterate($username.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        $user = User::query()->where('username', $username)->first();

        // The same message for an unknown username and a wrong PIN, and a hash
        // check either way, so neither is distinguishable from outside.
        $valid = Hash::check($credentials['pin'], $user->pin ?? Hash::make(Str::random()));

        if (! $user || ! $user->pin || ! $valid) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'pin' => "That username and PIN don't match.",
            ]);
        }

        // A PIN must not be a way around two-factor authentication.
        if ($user->two_factor_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'pin' => 'This account uses two-factor sign in. Log in with your email and password.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate();

        // A tablet whose session expired mid-shift signs back in without leaving
        // the page, so its queued scans are not lost.
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $user->getKey(), 'name' => $user->name]]);
        }

        return redirect()->intended(config('fortify.home'));
    }
}
