<?php

namespace Modules\Procurement\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequest extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'rejected' => 'Rejected', 'ordered' => 'Ordered', 'closed' => 'Closed'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'procurement_requests';

    protected $fillable = [
        'purchase_order_id',
        'requested_by_id',
        'department_id',
        'id', 'organization_id', 'reference', 'requested_by', 'department', 'title', 'status', 'priority', 'estimated_minor', 'requested_on', 'needed_by', 'justification',
    ];

    protected $casts = [
        'estimated_minor' => 'integer', 'requested_on' => 'date', 'needed_by' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\Modules\Departments\Models\Department::class, 'department_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(\App\Models\OrganizationMember::class, 'requested_by_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\Modules\Purchasing\Models\PurchaseOrder::class, 'purchase_order_id');
    }
}
