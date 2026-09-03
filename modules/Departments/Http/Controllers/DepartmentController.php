<?php

namespace Modules\Departments\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Departments\Models\Department;

class DepartmentController extends ResourceModuleController
{
    protected string $module = 'departments';

    protected string $model = Department::class;

    protected string $title = 'Department';

    protected string $orderBy = 'name';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['name', 'code', 'head'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['code' => 'department'];

    protected function routeBase(): string
    {
        return 'departments.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('name', __('Department'))->required(),
            Field::text('head', __('Head of department')),
            Field::text('cost_centre', __('Cost centre'), 60),
            Field::money('budget', __('Annual budget')),
            Field::checkbox('active', __('Active'), true),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'name' => __('Department'),
            'code' => __('Code'),
            'head' => __('Head'),
            'budget_minor' => __('Budget'),
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

        $data['budget_minor'] = Money::toMinor($data['budget'] ?? 0);
            unset($data['budget']);

        return $data;
    }
}
