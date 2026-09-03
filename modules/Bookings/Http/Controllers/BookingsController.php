<?php

namespace Modules\Bookings\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Bookings\Models\Appointment;

class BookingsController extends ModuleController
{
    protected string $module = 'bookings';

    public function index()
    {
        return view('bookings::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Appointment::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Appointment::query())->sum('price_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Appointment::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new AppointmentController)->listColumns(),
        ]);
    }
}
