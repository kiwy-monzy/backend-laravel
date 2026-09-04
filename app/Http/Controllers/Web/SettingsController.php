<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The signed-in user's own account, plus the chrome preferences.
 *
 * Theme, text size and language are session state rather than columns: they
 * describe this browser, not this person, and adding three columns to `users`
 * for them would mean a migration every time the chrome grows a knob.
 */
class SettingsController extends AdminController
{
    public function edit()
    {
        return view('settings.edit', [
            'user' => $this->me(),
            'site' => $this->site(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->me();

        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:60', 'unique:users,username,'.$user->id.',id'],
            'email' => ['nullable', 'email'],
            'font' => ['nullable', 'in:small,normal,large'],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['new_password'])) {
            // Proof of the current password, because a hijacked session must
            // not be enough to lock the real owner out of their own account.
            if (! Hash::check($data['current_password'] ?? '', $user->password_hash)) {
                return back()->withErrors(['current_password' => __('That is not your current password.')]);
            }
            $user->password_hash = Hash::make($data['new_password']);
        }

        if (array_key_exists('username', $data) && filled($data['username'])) {
            $user->username = $data['username'];
        }
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $user->email = $data['email'];
        }

        $user->save();

        if (! empty($data['font'])) {
            session(['chrome.font' => $data['font']]);
        }

        return back()->with('status', __('Settings saved.'));
    }

    public function theme(Request $request): RedirectResponse
    {
        session(['chrome.theme' => session('chrome.theme', 'light') === 'dark' ? 'light' : 'dark']);

        return back();
    }

    public function locale(Request $request): RedirectResponse
    {
        $locale = $request->input('locale');
        if (in_array($locale, ['en', 'sw'], true)) {
            session(['chrome.locale' => $locale]);
        }

        return back();
    }
}
