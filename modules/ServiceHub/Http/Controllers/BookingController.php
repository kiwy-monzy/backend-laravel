<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Support\Field;
use Illuminate\Http\Request;
use Modules\Invoicing\Models\Money;
use Modules\ServiceHub\Models\Booking;
use Modules\ServiceHub\Models\Provider;

class BookingController extends ServiceHubResourceController
{
    protected string $model = Booking::class;

    protected string $title = 'Service booking';

    protected string $orderBy = 'scheduled_at';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['reference', 'customer', 'address'];

    protected array $gridWith = ['provider', 'service'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['reference' => 'service_booking'];

    protected function routeBase(): string
    {
        return 'servicehub.bookings';
    }

    protected function fields(): array
    {
        return [
            Field::text('customer', __('Customer'))->required(),
            Field::select('provider_id', __('Provider'), $this->providerOptions(), ''),
            Field::select('service_id', __('Service'), $this->serviceOptions(), ''),
            Field::text('scheduled_at', __('Scheduled for'))->required()->help(__('YYYY-MM-DD HH:MM')),
            Field::number('duration_minutes', __('Duration (minutes)'), 5, 5)->default(60),
            Field::text('address', __('Address')),
            Field::select('status', __('Status'), Booking::STATUSES, 'pending'),
            Field::select('payment_status', __('Payment'), Booking::PAYMENT_STATUSES, 'unpaid'),
            Field::money('amount', __('Amount')),
            Field::money('commission', __('Commission'))
                ->help(__('Left at zero, this is worked out from the provider’s rate.')),
            Field::textarea('notes', __('Notes')),
        ];
    }

    protected function columns(): array
    {
        return [
            'reference' => __('Reference'),
            'customer' => __('Customer'),
            'provider_id' => __('Provider'),
            'scheduled_at' => __('Scheduled'),
            'status' => __('Status'),
            'payment_status' => __('Payment'),
            'amount_minor' => __('Amount'),
        ];
    }

    protected function gridValue($record, string $column): mixed
    {
        if ($column === 'provider_id') {
            return $record->provider?->name ?? '—';
        }

        if ($column === 'service_id') {
            return $record->service?->name ?? '—';
        }

        return parent::gridValue($record, $column);
    }

    /**
     * The form works in major units; the columns store minor.
     *
     * A commission left at zero is filled from the provider's rate rather than
     * saved as zero: the usual case is that nobody wants to do the arithmetic,
     * and a booking that silently earns the organization nothing is a worse
     * default than one that applies the rate on file.
     */
    protected function validated(Request $request): array
    {
        $data = parent::validated($request);

        $data['amount_minor'] = Money::toMinor($data['amount'] ?? 0);
        $data['commission_minor'] = Money::toMinor($data['commission'] ?? 0);
        unset($data['amount'], $data['commission']);

        foreach (['provider_id', 'service_id'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }

        if ($data['commission_minor'] === 0 && $data['amount_minor'] > 0 && $data['provider_id']) {
            $rate = (float) (Provider::where('organization_id', $this->organizationId())
                ->find($data['provider_id'])?->commission_percent
                ?? config('servicehub.default_commission', 15));

            $data['commission_minor'] = (int) round($data['amount_minor'] * $rate / 100);
        }

        return $data;
    }
}
