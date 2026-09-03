<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Purchasing\Models\PurchaseOrder;

class PurchasingController extends ModuleController
{
    protected string $module = 'purchasing';

    public function index()
    {
        return view('purchasing::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(PurchaseOrder::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(PurchaseOrder::query())->sum('total_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(PurchaseOrder::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new PurchaseOrderController)->listColumns(),
        ]);
    }
}
