<?php

namespace Modules\Projects\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Projects\Models\Project;

class ProjectsController extends ModuleController
{
    protected string $module = 'projects';

    public function index()
    {
        return view('projects::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Project::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Project::query())->sum('budget_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Project::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new ProjectController)->listColumns(),
        ]);
    }
}
