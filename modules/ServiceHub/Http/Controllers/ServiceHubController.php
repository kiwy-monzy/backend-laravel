<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\ServiceHub\Models\Booking;
use Modules\ServiceHub\Models\Provider;
use Modules\ServiceHub\Models\Service;
use Modules\ServiceHub\Models\ServiceRequest;

class ServiceHubController extends ModuleController
{
    protected string $module = 'servicehub';

    public function index()
    {
        $currency = $this->organization()?->currency ?? 'TZS';

        return view('servicehub::index', [
            'organization' => $this->organization(),

            'providers' => $this->scopedToOrg(Provider::query())->count(),
            'pendingProviders' => $this->scopedToOrg(Provider::query())->where('status', 'pending')->count(),
            'services' => $this->scopedToOrg(Service::query())->where('active', true)->count(),
            'openRequests' => $this->scopedToOrg(ServiceRequest::query())
                ->whereIn('status', ServiceRequest::OPEN)->count(),
            'bookings' => $this->scopedToOrg(Booking::query())->count(),

            // Only completed work is earnings; counting cancelled bookings is
            // how a dashboard ends up flattering the month it reports on.
            'earned' => Money::format(
                (int) $this->scopedToOrg(Booking::query())->where('status', 'completed')->sum('commission_minor'),
                $currency,
            ),
            'booked' => Money::format(
                (int) $this->scopedToOrg(Booking::query())->where('status', '!=', 'cancelled')->sum('amount_minor'),
                $currency,
            ),

            'recentRequests' => $this->scopedToOrg(ServiceRequest::query())
                ->with('provider')->orderByDesc('created_at')->limit(8)->get(),
            'upcoming' => $this->scopedToOrg(Booking::query())
                ->with('provider')
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->orderBy('scheduled_at')
                ->limit(8)
                ->get(),
            'currency' => $currency,
        ]);
    }
}
