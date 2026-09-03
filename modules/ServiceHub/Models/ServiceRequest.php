<?php

namespace Modules\ServiceHub\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a customer asked for, before anyone agreed to do it.
 *
 * **Kept apart from the booking it becomes.** A request can be declined, can
 * go to three providers before one accepts, and can be raised for a date
 * nobody is free on; a booking is a commitment with a provider and a price on
 * it. Folding the two together is what turns "show me every open request" into
 * a query with three status columns in it.
 */
class ServiceRequest extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'accepted' => 'Accepted',
        'booked' => 'Booked',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses that still want somebody's attention. */
    public const OPEN = ['pending', 'assigned', 'accepted'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'servicehub_requests';

    protected $fillable = [
        'id', 'organization_id', 'reference', 'customer_id', 'customer', 'phone', 'email',
        'service_id', 'category', 'description', 'preferred_at', 'address', 'zone',
        'budget_minor', 'status', 'provider_id',
    ];

    protected $casts = [
        'preferred_at' => 'datetime',
        'budget_minor' => 'integer',
    ];

    /** Major units for the form; see {@see Service::getPriceAttribute()}. */
    public function getBudgetAttribute(): float
    {
        return \Modules\Invoicing\Models\Money::toDecimal((int) $this->budget_minor);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'request_id');
    }

    public function customerRecord(): BelongsTo
    {
        return $this->belongsTo(\Modules\Crm\Models\Customer::class, 'customer_id');
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
