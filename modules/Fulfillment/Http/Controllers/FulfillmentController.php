<?php

namespace Modules\Fulfillment\Http\Controllers;

use App\Http\Controllers\Web\ModuleController;
use Modules\Fulfillment\Models\Shipment;

class FulfillmentController extends ModuleController
{
    protected string $module = 'fulfillment';

    public function index()
    {
        return view('fulfillment::index', [
            'organization' => $this->organization(),
            'count' => $this->scopedToOrg(Shipment::query())->count(),
            'recent' => $this->scopedToOrg(Shipment::query())
                ->orderByDesc('created_at')->limit(10)->get(),
            'columns' => (new ShipmentController)->listColumns(),
        ]);
    }
}
