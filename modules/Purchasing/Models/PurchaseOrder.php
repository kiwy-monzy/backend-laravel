<?php

namespace Modules\Purchasing\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    public const STATUSES = ['draft' => 'Draft', 'issued' => 'Issued', 'partially_received' => 'Partially received', 'received' => 'Received', 'billed' => 'Billed', 'cancelled' => 'Cancelled'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'purchasing_orders';

    protected $fillable = [
        'vendor_id',
        'id', 'organization_id', 'number', 'vendor', 'status', 'ordered_on', 'expected_on', 'total_minor', 'currency', 'reference', 'notes',
    ];

    protected $casts = [
        'ordered_on' => 'date', 'expected_on' => 'date', 'total_minor' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\Modules\Procurement\Models\Vendor::class, 'vendor_id');
    }
}
