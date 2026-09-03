<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Inventory\Models\Stock;

class InventoryController extends ModuleController
{
    protected string $module = 'inventory';

    public function index()
    {
        return view('inventory::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Stock::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Stock::query())->sum('unit_cost_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Stock::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new StockController)->listColumns(),
        ]);
    }
}
