<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Support\Field;
use Illuminate\Http\Request;
use Modules\Invoicing\Models\Money;
use Modules\ServiceHub\Models\Service;

class ServiceController extends ServiceHubResourceController
{
    protected string $model = Service::class;

    protected string $title = 'Service';

    protected string $orderBy = 'name';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['name', 'category', 'description'];

    protected array $gridWith = ['provider'];

    protected function routeBase(): string
    {
        return 'servicehub.services';
    }

    protected function fields(): array
    {
        return [
            Field::text('name', __('Service'))->required(),
            Field::select('provider_id', __('Provider'), $this->providerOptions(false), ''),
            Field::text('category', __('Category')),
            Field::money('price', __('Price')),
            Field::number('duration_minutes', __('Duration (minutes)'), 5, 5)->default(60),
            Field::checkbox('active', __('Offered'), true),
            Field::textarea('description', __('Description')),
        ];
    }

    protected function columns(): array
    {
        return [
            'name' => __('Service'),
            'category' => __('Category'),
            'provider_id' => __('Provider'),
            'price_minor' => __('Price'),
            'duration_minutes' => __('Minutes'),
            'active' => __('Offered'),
        ];
    }

    /** The provider column holds an id; the list has to print a name. */
    protected function gridValue($record, string $column): mixed
    {
        if ($column === 'provider_id') {
            return $record->provider?->name ?? '—';
        }

        return parent::gridValue($record, $column);
    }

    /**
     * The form works in major units; the column stores minor.
     *
     * Converting here rather than in the model keeps the single rounding step
     * where the string from the browser is first turned into a number.
     */
    protected function validated(Request $request): array
    {
        $data = parent::validated($request);

        $data['price_minor'] = Money::toMinor($data['price'] ?? 0);
        unset($data['price']);

        return $data;
    }
}
