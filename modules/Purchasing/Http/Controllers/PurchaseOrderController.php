<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Purchasing\Models\PurchaseOrder;

class PurchaseOrderController extends ResourceModuleController
{
    protected string $module = 'purchasing';

    protected string $model = PurchaseOrder::class;

    protected string $title = 'Purchase order';

    protected string $orderBy = 'ordered_on';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['number', 'vendor', 'reference'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['number' => 'purchase_order'];

    protected function routeBase(): string
    {
        return 'purchasing.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('vendor', __('Vendor'))->required(),
            Field::select('status', __('Status'), PurchaseOrder::STATUSES, 'draft'),
            Field::date('ordered_on', __('Ordered on'))->required(),
            Field::date('expected_on', __('Expected')),
            Field::money('total', __('Total')),
            Field::text('reference', __('Reference'), 120),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'number' => __('Number'),
            'vendor' => __('Vendor'),
            'status' => __('Status'),
            'ordered_on' => __('Ordered'),
            'expected_on' => __('Expected'),
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
