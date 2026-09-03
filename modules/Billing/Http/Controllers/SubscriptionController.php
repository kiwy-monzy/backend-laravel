<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Billing\Models\Subscription;

class SubscriptionController extends ResourceModuleController
{
    protected string $module = 'billing';

    protected string $model = Subscription::class;

    protected string $title = 'Subscription';

    protected string $orderBy = 'next_charge_on';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['customer', 'plan_name'];

    protected function routeBase(): string
    {
        return 'billing.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('customer', __('Customer'))->required(),
            Field::text('plan_name', __('Plan'))->required(),
            Field::select('status', __('Status'), Subscription::STATUSES, 'active'),
            Field::select('interval', __('Billed'), Subscription::INTERVALS, 'monthly'),
            Field::money('amount', __('Amount per period'))->required(),
            Field::date('started_on', __('Started'))->required(),
            Field::date('next_charge_on', __('Next charge')),
            Field::date('ends_on', __('Ends')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'customer' => __('Customer'),
            'plan_name' => __('Plan'),
            'status' => __('Status'),
            'interval' => __('Interval'),
            'amount_minor' => __('Amount'),
            'next_charge_on' => __('Next charge'),
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
