<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Accounting\Models\Account;

class AccountingController extends ModuleController
{
    protected string $module = 'accounting';

    public function index()
    {
        return view('accounting::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Account::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Account::query())->sum('opening_balance_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Account::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new AccountController)->listColumns(),
        ]);
    }
}
