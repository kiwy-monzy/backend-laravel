<?php

namespace Modules\Expenses\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Expenses\Models\Expense;

class ExpenseController extends ResourceModuleController
{
    protected string $module = 'expenses';

    protected string $model = Expense::class;

    protected string $title = 'Expense';

    protected string $orderBy = 'spent_on';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['account', 'vendor', 'reference'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['reference' => 'expense'];

    protected function routeBase(): string
    {
        return 'expenses.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('account', __('Expense account'))->required(),
            Field::text('vendor', __('Paid to')),
            Field::money('amount', __('Amount'))->required(),
            Field::date('spent_on', __('Spent on'))->required(),
            Field::select('status', __('Status'), Expense::STATUSES, 'draft'),
            Field::select('payment_method', __('Paid by'), Expense::METHODS, 'cash'),
            Field::checkbox('billable', __('Rebillable to a customer')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'account' => __('Account'),
            'vendor' => __('Paid to'),
            'amount_minor' => __('Amount'),
            'spent_on' => __('Spent on'),
            'status' => __('Status'),
        ];
    }

    /**
     * The form works in major units; the column stores minor.
     *
     * Converting here rather than in the model keeps the single rounding step
     * where the string from the browser is first turned into a number.
     */
    protected function validated(\Illuminate\Http\Request $request): array
    {
        $data = parent::validated($request);

        $data['amount_minor'] = Money::toMinor($data['amount'] ?? 0);
            unset($data['amount']);

        return $data;
    }
}
