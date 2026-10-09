<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PinController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/pin', [
            'username' => $request->user()->username,
            'hasPin' => $request->user()->pin !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_pin' => ['nullable', 'string'],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
        ], [
            'pin.regex' => 'A PIN is 4 to 6 digits.',
        ]);

        // Changing an existing PIN needs the current one.
        if ($user->pin !== null && ! Hash::check($validated['current_pin'] ?? '', $user->pin)) {
            throw ValidationException::withMessages([
                'current_pin' => 'That is not your current PIN.',
            ]);
        }

        $user->forceFill(['pin' => $validated['pin']])->save();

        return back()->with('status', 'PIN updated.');
    }
}
