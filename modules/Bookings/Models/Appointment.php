<?php

namespace Modules\Bookings\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    public const STATUSES = ['booked' => 'Booked', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'no_show' => 'No show', 'cancelled' => 'Cancelled'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'bookings_appointments';

    protected $fillable = [
        'staff_member_id',
        'customer_id',
        'id', 'organization_id', 'service', 'customer', 'staff', 'status', 'starts_at', 'duration_minutes', 'location', 'price_minor', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'duration_minutes' => 'integer', 'price_minor' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Modules\Crm\Models\Customer::class, 'customer_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(\App\Models\OrganizationMember::class, 'staff_member_id');
    }
}
