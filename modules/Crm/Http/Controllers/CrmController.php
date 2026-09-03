<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Crm\Models\Customer;

class CrmController extends ModuleController
{
    protected string $module = 'crm';

    public function index()
    {
        $customers = $this->scopedToOrg(Customer::query());

        return view('crm::index', [
            'organization' => $this->organization(),
            'counts' => [
                'customers' => (clone $customers)->where('contact_type', 'customer')->count(),
                'vendors' => (clone $customers)->where('contact_type', 'vendor')->count(),
                'inactive' => (clone $customers)->where('active', false)->count(),
            ],
            'recent' => (clone $customers)->orderByDesc('created_at')->limit(8)->get(),
        ]);
    }
}
