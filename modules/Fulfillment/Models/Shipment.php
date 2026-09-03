<?php

namespace Modules\Fulfillment\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use \Modules\Zones\Models\Concerns\HasZones;

    /** A shipment has one destination, not a coverage area. */
    public function zoneRole(): string
    {
        return 'destination';
    }

    public function hasSingleZone(): bool
    {
        return true;
    }

    public const STATUSES = ['packed' => 'Packed', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'returned' => 'Returned', 'lost' => 'Lost'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'fulfillment_shipments';

    protected $fillable = [
        'order_id',
        'customer_id',
        'id', 'organization_id', 'reference', 'customer', 'carrier', 'tracking_number', 'status', 'shipped_on', 'delivered_on', 'packages', 'weight_kg', 'notes',
    ];

    protected $casts = [
        'shipped_on' => 'date', 'delivered_on' => 'date', 'packages' => 'integer', 'weight_kg' => 'float',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(\Modules\Cart\Models\Order::class, 'order_id');
    }
}
