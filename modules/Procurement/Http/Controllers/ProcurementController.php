<?php

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Procurement\Models\PurchaseRequest;

class ProcurementController extends ModuleController
{
    protected string $module = 'procurement';

    public function index()
    {
        return view('procurement::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(PurchaseRequest::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(PurchaseRequest::query())->sum('estimated_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(PurchaseRequest::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new PurchaseRequestController)->listColumns(),
        ]);
    }
}
