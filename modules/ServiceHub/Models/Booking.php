<?php

namespace Modules\ServiceHub\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider committed to a job at a time, for an amount.
 *
 * The commission is stored, not computed on read. The organization's cut can
 * be renegotiated with a provider at any time, and a booking settled last
 * quarter must keep the rate it was settled at — recomputing from the
 * provider's current percentage would quietly rewrite history every time
 * someone opened a report.
 */
class Booking extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid' => 'Unpaid',
        'partial' => 'Part paid',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'servicehub_bookings';

    protected $fillable = [
        'id', 'organization_id', 'reference', 'request_id', 'provider_id', 'service_id',
        'customer_id', 'customer', 'scheduled_at', 'duration_minutes', 'address',
        'status', 'payment_status', 'amount_minor', 'commission_minor', 'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'amount_minor' => 'integer',
        'commission_minor' => 'integer',
    ];

    /** Major units for the form; see {@see Service::getPriceAttribute()}. */
    public function getAmountAttribute(): float
    {
        return \Modules\Invoicing\Models\Money::toDecimal((int) $this->amount_minor);
    }

    public function getCommissionAttribute(): float
    {
        return \Modules\Invoicing\Models\Money::toDecimal((int) $this->commission_minor);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'request_id');
    }

    public function customerRecord(): BelongsTo
    {
        return $this->belongsTo(\Modules\Crm\Models\Customer::class, 'customer_id');
    }

    /** What the provider keeps once the organization has taken its cut. */
    public function payoutMinor(): int
    {
        return max(0, (int) $this->amount_minor - (int) $this->commission_minor);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + [
            'payout_minor' => $this->payoutMinor(),
            'created_at' => $this->created_at?->toRfc3339String(),
        ];
    }
}
