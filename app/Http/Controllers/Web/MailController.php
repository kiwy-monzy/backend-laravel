<?php

namespace App\Http\Controllers\Web;

use App\Models\MailConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The IMAP/SMTP account this operator reads mail through.
 *
 * One config per user, not per website: it is their mailbox credentials, and
 * two admins on the same site legitimately have different inboxes.
 */
class MailController extends AdminController
{
    public function index()
    {
        return view('mail.index', [
            'config' => MailConfig::where('user_id', $this->me()->id)->first(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string'],
            'incoming_host' => ['required', 'string', 'max:190'],
            'incoming_port' => ['required', 'integer', 'between:1,65535'],
            'incoming_protocol' => ['required', 'in:imap,pop3'],
            'incoming_security' => ['required', 'in:ssl,starttls,none'],
            'outgoing_host' => ['required', 'string', 'max:190'],
            'outgoing_port' => ['required', 'integer', 'between:1,65535'],
            'outgoing_security' => ['required', 'in:ssl,starttls,none'],
        ]);

        $config = MailConfig::firstOrNew(['user_id' => $this->me()->id]);

        // A blank password field means "leave it alone", so that re-saving the
        // host or port does not silently wipe the stored credential.
        $password = $data['password'] ?? null;
        unset($data['password']);

        $config->fill($data + ['user_id' => $this->me()->id, 'linked_at' => now()]);
        if (filled($password)) {
            $config->password = $password;
        }
        $config->save();

        return back()->with('status', __('Mail account saved.'));
    }
}
