<?php

namespace Modules\ServiceHub\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A catalogue entry: one thing a provider will come and do, at a price.
 *
 * A service outlives the bookings taken against it and is edited in place, so
 * a booking copies the amount it agreed rather than reading it back through
 * this row — last year's booking must not change when this year's price does.
 */
class Service extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'servicehub_services';

    protected $fillable = [
        'id', 'organization_id', 'provider_id', 'name', 'category', 'description',
        'price_minor', 'duration_minutes', 'active',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'duration_minutes' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * The form edits major units; the column stores minor.
     *
     * Without this the edit form reads `price` off the model, finds nothing,
     * and silently offers zero — so opening a service and pressing Save would
     * wipe its price.
     */
    public function getPriceAttribute(): float
    {
        return \Modules\Invoicing\Models\Money::toDecimal((int) $this->price_minor);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
