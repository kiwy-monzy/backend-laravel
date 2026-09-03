<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\AdminController;
use App\Models\Donation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DonationController extends AdminController
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    public function index(Request $request)
    {
        $status = $request->query('status');

        $donations = $this->scoped(Donation::query())
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('website::donations.index', [
            'donations' => $donations,
            'status' => $status,
            'statuses' => self::STATUSES,
            'approvedTotal' => (float) $this->scoped(Donation::query())
                ->where('status', 'approved')->sum('amount'),
        ]);
    }

    public function update(Request $request, Donation $donation): RedirectResponse
    {
        $this->guard($donation->website_id);

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', self::STATUSES)],
        ]);

        $donation->update(['status' => $data['status']]);

        return back()->with('status', __('Donation updated.'));
    }

    public function destroy(Donation $donation): RedirectResponse
    {
        $this->guard($donation->website_id);
        $donation->delete();

        return back()->with('status', __('Donation deleted.'));
    }
}
