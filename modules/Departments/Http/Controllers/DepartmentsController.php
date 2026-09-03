<?php

namespace Modules\Departments\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Departments\Models\Department;

class DepartmentsController extends ModuleController
{
    protected string $module = 'departments';

    public function index()
    {
        return view('departments::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Department::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Department::query())->sum('budget_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Department::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new DepartmentController)->listColumns(),
        ]);
    }
}
