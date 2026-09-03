<?php

namespace Modules\Bookings\Http\Controllers;

use App\Http\Controllers\Web\ResourceModuleController;
use App\Support\Field;
use Modules\Invoicing\Models\Money;
use Modules\Bookings\Models\Appointment;

class AppointmentController extends ResourceModuleController
{
    protected string $module = 'bookings';

    protected string $model = Appointment::class;

    protected string $title = 'Appointment';

    protected string $orderBy = 'starts_at';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['service', 'customer', 'staff'];

    protected function routeBase(): string
    {
        return 'bookings.records';
    }

    protected function fields(): array
    {
        return [
            Field::text('service', __('Service'))->required(),
            Field::text('customer', __('Customer')),
            Field::text('staff', __('Staff member')),
            Field::select('status', __('Status'), Appointment::STATUSES, 'booked'),
            Field::text('starts_at', __('Starts at'))->required()->help(__('YYYY-MM-DD HH:MM')),
            Field::number('duration_minutes', __('Duration (minutes)'), 5, 5),
            Field::text('location', __('Location')),
            Field::money('price', __('Price')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'service' => __('Service'),
            'customer' => __('Customer'),
            'staff' => __('Staff'),
            'starts_at' => __('Starts'),
            'status' => __('Status'),
            'price_minor' => __('Price'),
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

        $data['price_minor'] = Money::toMinor($data['price'] ?? 0);
            unset($data['price']);

        return $data;
    }
}
