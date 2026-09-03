<?php

namespace Modules\Workerly\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Workerly\Models\Shift;

class WorkerlyController extends ModuleController
{
    protected string $module = 'workerly';

    public function index()
    {
        return view('workerly::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Shift::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Shift::query())->sum('rate_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Shift::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new ShiftController)->listColumns(),
        ]);
    }
}
