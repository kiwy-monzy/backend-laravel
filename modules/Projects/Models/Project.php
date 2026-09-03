<?php

namespace Modules\Projects\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    public const STATUSES = ['active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public const BILLING = ['fixed' => 'Fixed fee', 'hourly' => 'Hourly', 'non_billable' => 'Non-billable'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'projects_records';

    protected $fillable = [
        'department_id',
        'contract_id',
        'customer_id',
        'id', 'organization_id', 'name', 'customer', 'code', 'status', 'billing_method', 'budget_minor', 'hourly_rate_minor', 'starts_on', 'ends_on', 'description',
    ];

    protected $casts = [
        'budget_minor' => 'integer', 'hourly_rate_minor' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date',
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(\Modules\Contracts\Models\Contract::class, 'contract_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\Departments\Models\Department::class, 'department_id');
    }
}
