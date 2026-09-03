<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Billing\Models\Subscription;

class BillingController extends ModuleController
{
    protected string $module = 'billing';

    public function index()
    {
        return view('billing::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Subscription::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Subscription::query())->sum('amount_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Subscription::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new SubscriptionController)->listColumns(),
        ]);
    }
}
