<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Accounting\Models\Account;

class AccountController extends ResourceModuleController
{
    protected string $module = 'accounting';

    protected string $model = Account::class;

    protected string $title = 'Account';

    protected string $orderBy = 'code';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['code', 'name'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['code' => 'account'];

    protected function routeBase(): string
    {
        return 'accounting.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('name', __('Account name'))->required(),
            Field::select('account_type', __('Type'), Account::TYPES, 'asset'),
            Field::text('parent_code', __('Parent code'), 20),
            Field::money('opening_balance', __('Opening balance')),
            Field::checkbox('active', __('Active'), true),
            Field::textarea('description', __('Description')),
        ];
    }

    protected function columns(): array
    {
        return [
            'code' => __('Code'),
            'name' => __('Account'),
            'account_type' => __('Type'),
            'opening_balance_minor' => __('Opening'),
            'active' => __('Active'),
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

        $data['opening_balance_minor'] = Money::toMinor($data['opening_balance'] ?? 0);
            unset($data['opening_balance']);

        return $data;
    }
}
