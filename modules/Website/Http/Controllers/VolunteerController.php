<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\AdminController;
use App\Models\Volunteer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VolunteerController extends AdminController
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public function index(Request $request)
    {
        $status = $request->query('status');

        return view('website::volunteers.index', [
            'volunteers' => $this->scoped(Volunteer::query())
                ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, Volunteer $volunteer): RedirectResponse
    {
        $this->guard($volunteer->website_id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
        ]);

        $volunteer->update(['status' => $data['status']]);

        return back()->with('status', __('Volunteer updated.'));
    }

    public function destroy(Volunteer $volunteer): RedirectResponse
    {
        $this->guard($volunteer->website_id);
        $volunteer->delete();

        return back()->with('status', __('Volunteer deleted.'));
    }
}
