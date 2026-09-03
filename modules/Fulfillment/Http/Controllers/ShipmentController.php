<?php

namespace Modules\Fulfillment\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Fulfillment\Models\Shipment;

class ShipmentController extends ResourceModuleController
{
    protected string $module = 'fulfillment';

    protected string $model = Shipment::class;

    protected string $title = 'Shipment';

    protected string $orderBy = 'shipped_on';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['reference', 'customer', 'tracking_number', 'carrier'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['reference' => 'shipment'];

    protected function routeBase(): string
    {
        return 'fulfillment.records';
    }

    /** The shipment form carries the area it is going to. */
    protected function formExtras(?\Illuminate\Database\Eloquent\Model $record): array
    {
        return ['formActions' => 'zones::picker', 'zoneKind' => 'fulfillment-shipment'];
    }

    protected function fields(): array
    {
        return [
            Field::text('customer', __('Customer')),
            Field::text('carrier', __('Carrier'), 90),
            Field::text('tracking_number', __('Tracking number'), 90),
            Field::select('status', __('Status'), Shipment::STATUSES, 'packed'),
            Field::date('shipped_on', __('Shipped on')),
            Field::date('delivered_on', __('Delivered on')),
            Field::number('packages', __('Packages'), 1, 1),
            Field::number('weight_kg', __('Weight (kg)'), 0, 0.001),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'reference' => __('Reference'),
            'customer' => __('Customer'),
            'carrier' => __('Carrier'),
            'status' => __('Status'),
            'shipped_on' => __('Shipped'),
            'tracking_number' => __('Tracking'),
        ];
    }
}
