<?php

namespace Modules\Cart\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Cart\Models\Order;

class OrderController extends ResourceModuleController
{
    protected string $module = 'cart';

    protected string $model = Order::class;

    protected string $title = 'Order';

    protected string $orderBy = 'ordered_on';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['number', 'customer_name'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['number' => 'order'];

    protected function routeBase(): string
    {
        return 'cart.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('customer_name', __('Customer')),
            Field::select('channel', __('Channel'), Order::CHANNELS, 'in_person'),
            Field::select('status', __('Status'), Order::STATUSES, 'draft'),
            Field::date('ordered_on', __('Ordered on'))->required(),
            Field::date('required_on', __('Required by')),
            Field::money('total', __('Order total')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'number' => __('Number'),
            'customer_name' => __('Customer'),
            'channel' => __('Channel'),
            'status' => __('Status'),
            'ordered_on' => __('Ordered'),
            'total_minor' => __('Total'),
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

        $data['total_minor'] = Money::toMinor($data['total'] ?? 0);
            unset($data['total']);

        return $data;
    }
}
