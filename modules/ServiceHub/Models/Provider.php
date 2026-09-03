<?php

namespace Modules\ServiceHub\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Zones\Models\Concerns\HasZones;

/**
 * A firm or individual that supplies services through the hub.
 *
 * **Onboarding is a status, not a flag.** An applicant who has been denied and
 * one who has not applied need different answers from the same list, and a
 * boolean cannot give them — which is why `status` is separate from `active`:
 * the first is where the provider stands with us, the second is whether they
 * are taking work this week.
 */
class Provider extends Model
{
    /** A provider travels to any number of areas; `zone` above is the free-text one it is based in. */
    use HasZones;

    public const STATUSES = [
        'pending' => 'Pending review',
        'approved' => 'Approved',
        'suspended' => 'Suspended',
        'denied' => 'Denied',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'servicehub_providers';

    protected $fillable = [
        'id', 'organization_id', 'code', 'name', 'contact_name', 'email', 'phone',
        'address', 'zone', 'status', 'commission_percent', 'rating', 'active', 'notes',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'rating' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    /** Providers that may actually be assigned work. */
    public function scopeBookable($query)
    {
        return $query->where('status', 'approved')->where('active', true);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
