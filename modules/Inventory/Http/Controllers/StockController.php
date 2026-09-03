<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Inventory\Models\Stock;

class StockController extends ResourceModuleController
{
    protected string $module = 'inventory';

    protected string $model = Stock::class;

    protected string $title = 'Stock item';

    protected string $orderBy = 'item_name';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['item_name', 'sku', 'location', 'batch'];

    protected function routeBase(): string
    {
        return 'inventory.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('item_name', __('Item'))->required(),
            Field::text('sku', __('SKU'), 60),
            Field::text('location', __('Location'), 90)->default('Main'),
            Field::number('quantity', __('Quantity on hand'), 0, 0.001)->required(),
            Field::number('reorder_level', __('Reorder at'), 0, 0.001),
            Field::money('unit_cost', __('Unit cost')),
            Field::text('batch', __('Batch / lot'), 60),
            Field::date('expires_on', __('Expires on')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'item_name' => __('Item'),
            'sku' => __('SKU'),
            'location' => __('Location'),
            'quantity' => __('On hand'),
            'reorder_level' => __('Reorder at'),
            'expires_on' => __('Expires'),
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

        $data['unit_cost_minor'] = Money::toMinor($data['unit_cost'] ?? 0);
            unset($data['unit_cost']);

        return $data;
    }
}
