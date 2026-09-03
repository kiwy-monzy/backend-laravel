<?php

namespace Modules\Assets\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Assets\Models\Asset;

class AssetController extends ResourceModuleController
{
    protected string $module = 'assets';

    protected string $model = Asset::class;

    protected string $title = 'Asset';

    protected string $orderBy = 'name';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['name', 'tag', 'serial_number', 'assigned_to'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['tag' => 'asset'];

    protected function routeBase(): string
    {
        return 'assets.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('name', __('Asset'))->required(),
            Field::select('category', __('Category'), Asset::CATEGORIES, 'equipment'),
            Field::text('serial_number', __('Serial number'), 90),
            Field::text('assigned_to', __('Assigned to'))->help(__('The employee holding it')),
            Field::text('department', __('Department')),
            Field::text('location', __('Location')),
            Field::select('status', __('Status'), Asset::STATUSES, 'in_use'),
            Field::select('condition', __('Condition'), Asset::CONDITIONS, 'good'),
            Field::date('purchased_on', __('Purchased on')),
            Field::money('purchase_cost', __('Purchase cost')),
            Field::money('current_value', __('Current book value')),
            Field::number('useful_life_years', __('Useful life (years)'), 0, 1),
            Field::date('warranty_until', __('Warranty until')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'name' => __('Asset'),
            'tag' => __('Tag'),
            'category' => __('Category'),
            'assigned_to' => __('Assigned to'),
            'status' => __('Status'),
            'current_value_minor' => __('Book value'),
        ];
    }

    /**
     * `assigned_to` is the legacy typed name; the holder is a real seat now,
     * so the column prints whoever actually has the thing.
     */
    protected function gridValue($record, string $column): mixed
    {
        if ($column === 'assigned_to') {
            return $record->holderLabel();
        }

        return parent::gridValue($record, $column);
    }

    /** Loaded for the grid, so printing the holder is not a query per row. */
    protected array $gridWith = ['holder'];

    /**
     * The form works in major units; the column stores minor.
     *
     * Converting here rather than in the model keeps the single rounding step
     * where the string from the browser is first turned into a number.
     */
    protected function validated(\Illuminate\Http\Request $request): array
    {
        $data = parent::validated($request);

        $data['purchase_cost_minor'] = Money::toMinor($data['purchase_cost'] ?? 0);
            unset($data['purchase_cost']);
            $data['current_value_minor'] = Money::toMinor($data['current_value'] ?? 0);
            unset($data['current_value']);

        return $data;
    }
}
