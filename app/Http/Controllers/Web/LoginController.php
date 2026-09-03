<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Session sign-in for the admin pages.
 *
 * Separate from `Api\AuthController`, which issues JWTs for the packaged
 * frontend. Both read the same `users.password_hash`, so an account works in
 * either without a second credential.
 */
class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $ok = Auth::attempt(
            ['username' => $data['username'], 'password' => $data['password'], 'active' => true],
            (bool) ($data['remember'] ?? false),
        );

        if (! $ok) {
            // One message for both "no such user" and "wrong password": telling
            // them apart tells an attacker which usernames exist.
            throw ValidationException::withMessages([
                'username' => __('Those details do not match an active account.'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        return redirect()->intended(
            $user->hasAdminAccess() ? route('dashboard') : route('settings.edit')
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
