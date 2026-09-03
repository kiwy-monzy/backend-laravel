<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\AdminController;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends AdminController
{
    public const STATUSES = ['pending', 'read', 'archived'];

    public function index(Request $request)
    {
        $status = $request->query('status');

        return view('website::messages.index', [
            'messages' => $this->scoped(Message::query())
                ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Message $message): RedirectResponse
    {
        $this->guard($message->website_id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
        ]);

        // `is_read` drives the sidebar badge, so it has to follow the status
        // rather than be a second thing someone has to remember to set.
        $message->update([
            'status' => $data['status'],
            'is_read' => $data['status'] !== 'pending',
        ]);

        return back()->with('status', __('Message updated.'));
    }

    public function destroy(Message $message): RedirectResponse
    {
        $this->guard($message->website_id);
        $message->delete();

        return back()->with('status', __('Message deleted.'));
    }
}
