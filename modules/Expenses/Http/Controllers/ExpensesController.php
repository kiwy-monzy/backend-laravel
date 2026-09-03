<?php

namespace Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Expenses\Models\Expense;

class ExpensesController extends ModuleController
{
    protected string $module = 'expenses';

    public function index()
    {
        return view('expenses::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Expense::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Expense::query())->sum('amount_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Expense::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new ExpenseController)->listColumns(),
        ]);
    }
}
