<?php

namespace Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Tickets\Models\Ticket;

class TicketsController extends ModuleController
{
    protected string $module = 'tickets';

    public function index()
    {
        return view('tickets::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Ticket::query())->count(),
            'recent' => $this->scopedToOrg(Ticket::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new TicketController)->listColumns(),
        ]);
    }
}
