<?php

namespace Modules\Assets\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Invoicing\Models\Money;
use Modules\Assets\Models\Asset;

class AssetsController extends ModuleController
{
    protected string $module = 'assets';

    public function index()
    {
        return view('assets::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Asset::query())->count(),
            'total' => Money::format(
                (int) $this->scopedToOrg(Asset::query())->sum('purchase_cost_minor'),
                $this->organization()?->currency ?? 'TZS',
            ),
            'recent' => $this->scopedToOrg(Asset::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new AssetController)->listColumns(),
        ]);
    }
}
