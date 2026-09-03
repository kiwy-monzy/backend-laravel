<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Support\Field;
use Modules\ServiceHub\Models\Provider;

class ProviderController extends ServiceHubResourceController
{
    protected string $model = Provider::class;

    protected string $title = 'Provider';

    protected string $orderBy = 'name';

    protected string $orderDirection = 'asc';

    protected array $searchable = ['name', 'code', 'contact_name', 'email', 'phone', 'zone'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['code' => 'service_provider'];

    protected function routeBase(): string
    {
        return 'servicehub.providers';
    }

    /** The provider form carries its coverage areas. */
    protected function formExtras(?\Illuminate\Database\Eloquent\Model $record): array
    {
        return ['formActions' => 'zones::picker', 'zoneKind' => 'servicehub-provider'];
    }

    protected function fields(): array
    {
        return [
            Field::text('name', __('Provider'))->required(),
            Field::text('contact_name', __('Contact person')),
            Field::email('email', __('Email')),
            Field::text('phone', __('Phone'), 60),
            Field::text('address', __('Address')),
            Field::text('zone', __('Zone'))->help(__('The area this provider covers.')),
            Field::select('status', __('Onboarding'), Provider::STATUSES, 'pending'),
            Field::number('commission_percent', __('Commission %'), 0, 0.01)
                ->default(config('servicehub.default_commission', 15))
                ->help(__('The share this organization keeps on each booking.')),
            Field::number('rating', __('Rating'), 0, 0.01),
            Field::checkbox('active', __('Taking work'), true),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'name' => __('Provider'),
            'code' => __('Code'),
            'phone' => __('Phone'),
            'zone' => __('Zone'),
            'status' => __('Onboarding'),
            'commission_percent' => __('Commission %'),
            'active' => __('Taking work'),
        ];
    }
}
