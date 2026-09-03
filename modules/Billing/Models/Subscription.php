<?php

namespace Modules\Billing\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled', 'past_due' => 'Past due'];

    public const INTERVALS = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'customer_id',
        'id', 'organization_id', 'customer', 'plan_name', 'status', 'interval', 'amount_minor', 'currency', 'started_on', 'next_charge_on', 'ends_on', 'notes',
    ];

    protected $casts = [
        'amount_minor' => 'integer', 'started_on' => 'date', 'next_charge_on' => 'date', 'ends_on' => 'date',
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
}
