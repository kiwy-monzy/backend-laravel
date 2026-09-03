<?php

namespace Modules\Cart\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Cart\Models\Order;

class CartController extends ModuleController
{
    protected string $module = 'cart';

    public function index()
    {
        return view('cart::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Order::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Order::query())->sum('total_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Order::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new OrderController)->listColumns(),
        ]);
    }
}
