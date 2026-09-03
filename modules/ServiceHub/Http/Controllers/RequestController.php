<?php

namespace Modules\ServiceHub\Http\Controllers;

use App\Support\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Invoicing\Models\Money;
use Modules\ServiceHub\Models\Booking;
use Modules\ServiceHub\Models\Provider;
use Modules\ServiceHub\Models\Service;
use Modules\ServiceHub\Models\ServiceRequest;

class RequestController extends ServiceHubResourceController
{
    protected string $model = ServiceRequest::class;

    protected string $title = 'Service request';

    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    protected array $searchable = ['reference', 'customer', 'phone', 'email', 'category', 'description'];

    protected array $gridWith = ['provider', 'service'];

    /** Allocated by the numbering service, never typed. */
    protected array $generated = ['reference' => 'service_request'];

    protected function routeBase(): string
    {
        return 'servicehub.requests';
    }

    protected function fields(): array
    {
        return [
            Field::text('customer', __('Customer'))->required(),
            Field::text('phone', __('Phone'), 60),
            Field::email('email', __('Email')),
            Field::select('service_id', __('Service'), $this->serviceOptions(), ''),
            Field::text('category', __('Category')),
            Field::text('preferred_at', __('Preferred time'))->help(__('YYYY-MM-DD HH:MM')),
            Field::text('address', __('Address')),
            Field::text('zone', __('Zone')),
            Field::money('budget', __('Budget')),
            Field::select('provider_id', __('Assigned provider'), $this->providerOptions(), ''),
            Field::select('status', __('Status'), ServiceRequest::STATUSES, 'pending'),
            Field::textarea('description', __('What is needed')),
        ];
    }

    protected function columns(): array
    {
        return [
            'reference' => __('Reference'),
            'customer' => __('Customer'),
            'service_id' => __('Service'),
            'zone' => __('Zone'),
            'preferred_at' => __('Preferred'),
            'provider_id' => __('Provider'),
            'status' => __('Status'),
        ];
    }

    /** Both id columns hold a key; the list has to print a name. */
    protected function gridValue($record, string $column): mixed
    {
        if ($column === 'provider_id') {
            return $record->provider?->name ?? '—';
        }

        if ($column === 'service_id') {
            return $record->service?->name ?? ($record->category ?: '—');
        }

        return parent::gridValue($record, $column);
    }

    protected function validated(Request $request): array
    {
        $data = parent::validated($request);

        $data['budget_minor'] = Money::toMinor($data['budget'] ?? 0);
        unset($data['budget']);

        // An empty select posts '', and '' in a foreign key column is a value
        // that matches nothing but is not null — which makes `whereNull` lie.
        foreach (['provider_id', 'service_id'] as $key) {
            $data[$key] = $data[$key] ?: null;
        }

        // Assigning somebody is the act of assigning; making the user also
        // remember to change the status is how lists fill up with assigned
        // work still reading "pending".
        if ($data['provider_id'] && ($data['status'] ?? 'pending') === 'pending') {
            $data['status'] = 'assigned';
        }

        return $data;
    }

    /** The form carries one extra action: book it. */
    protected function formExtras(?\Illuminate\Database\Eloquent\Model $record): array
    {
        return ['formActions' => 'servicehub::request-actions'];
    }

    /**
     * Turn a request into a booking.
     *
     * **The conversion lives here rather than in the booking form** because
     * everything the booking needs is already on the request — retyping the
     * customer, the address and the provider is how the two rows end up
     * disagreeing about the same job.
     *
     * The amount comes from the catalogue price where there is one and the
     * customer's budget otherwise, and the commission is worked out from the
     * provider's rate *now* and then stored, so renegotiating the rate later
     * does not rewrite what this booking was worth.
     */
    public function convert(string $id): RedirectResponse
    {
        $this->authorizeAction('add');

        $record = $this->findScoped($id);

        if (! $record->provider_id) {
            return back()->with('status', __('Assign a provider before booking this request.'));
        }

        if ($existing = $record->bookings()->first()) {
            return redirect()
                ->route('servicehub.bookings.edit', $existing)
                ->with('status', __('This request was already booked.'));
        }

        $service = $record->service_id
            ? Service::where('organization_id', $this->organizationId())->find($record->service_id)
            : null;

        $provider = Provider::where('organization_id', $this->organizationId())->find($record->provider_id);

        $amountMinor = (int) ($service?->price_minor ?: $record->budget_minor);
        $rate = (float) ($provider?->commission_percent ?? config('servicehub.default_commission', 15));

        $booking = Booking::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'organization_id' => $this->organizationId(),
            'reference' => \App\Support\Sequences::next($this->organizationId(), 'service_booking'),
            'request_id' => $record->id,
            'provider_id' => $record->provider_id,
            'service_id' => $record->service_id,
            'customer_id' => $record->customer_id,
            'customer' => $record->customer,
            'scheduled_at' => $record->preferred_at,
            'duration_minutes' => $service?->duration_minutes ?: 60,
            'address' => $record->address,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'amount_minor' => $amountMinor,
            'commission_minor' => (int) round($amountMinor * $rate / 100),
        ]);

        $record->update(['status' => 'booked']);

        return redirect()
            ->route('servicehub.bookings.edit', $booking)
            ->with('status', __('Booking :reference created from this request.', ['reference' => $booking->reference]));
    }
}
