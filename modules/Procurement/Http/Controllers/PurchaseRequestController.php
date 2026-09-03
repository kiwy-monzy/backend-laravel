<?php

namespace Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Procurement\Models\PurchaseRequest;

class PurchaseRequestController extends ResourceModuleController
{
    protected string $module = 'procurement';

    protected string $model = PurchaseRequest::class;

    protected string $title = 'Purchase request';

    protected string $orderBy = 'requested_on';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['reference', 'title', 'requested_by', 'department'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['reference' => 'purchase_request'];

    protected function routeBase(): string
    {
        return 'procurement.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('title', __('What is needed'))->required(),
            Field::text('requested_by', __('Requested by')),
            Field::text('department', __('Department')),
            Field::select('status', __('Status'), PurchaseRequest::STATUSES, 'submitted'),
            Field::select('priority', __('Priority'), PurchaseRequest::PRIORITIES, 'normal'),
            Field::money('estimated', __('Estimated cost')),
            Field::date('requested_on', __('Requested on'))->required(),
            Field::date('needed_by', __('Needed by')),
            Field::textarea('justification', __('Justification')),
        ];
    }

    protected function columns(): array
    {
        return [
            'reference' => __('Reference'),
            'title' => __('What'),
            'department' => __('Department'),
            'status' => __('Status'),
            'priority' => __('Priority'),
            'estimated_minor' => __('Estimated'),
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

        $data['estimated_minor'] = Money::toMinor($data['estimated'] ?? 0);
            unset($data['estimated']);

        return $data;
    }
}
