<?php

namespace Modules\Projects\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Projects\Models\Project;

class ProjectController extends ResourceModuleController
{
    protected string $module = 'projects';

    protected string $model = Project::class;

    protected string $title = 'Project';

    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['name', 'customer', 'code'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['code' => 'project'];

    protected function routeBase(): string
    {
        return 'projects.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('name', __('Project'))->required(),
            Field::text('customer', __('Customer')),
            Field::select('status', __('Status'), Project::STATUSES, 'active'),
            Field::select('billing_method', __('Billing'), Project::BILLING, 'fixed'),
            Field::money('budget', __('Budget')),
            Field::money('hourly_rate', __('Hourly rate')),
            Field::date('starts_on', __('Starts')),
            Field::date('ends_on', __('Ends')),
            Field::textarea('description', __('Description')),
        ];
    }

    protected function columns(): array
    {
        return [
            'name' => __('Project'),
            'customer' => __('Customer'),
            'status' => __('Status'),
            'budget_minor' => __('Budget'),
            'starts_on' => __('Starts'),
            'ends_on' => __('Ends'),
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

        $data['budget_minor'] = Money::toMinor($data['budget'] ?? 0);
            unset($data['budget']);
            $data['hourly_rate_minor'] = Money::toMinor($data['hourly_rate'] ?? 0);
            unset($data['hourly_rate']);

        return $data;
    }
}
