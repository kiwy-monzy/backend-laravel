<?php

namespace Modules\Cart\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public const CHANNELS = ['in_person' => 'In person', 'phone' => 'Phone', 'website' => 'Website', 'whatsapp' => 'WhatsApp'];

    public const STATUSES = ['draft' => 'Draft', 'confirmed' => 'Confirmed', 'packed' => 'Packed', 'delivered' => 'Delivered', 'invoiced' => 'Invoiced', 'cancelled' => 'Cancelled'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'cart_orders';

    protected $fillable = [
        'shipment_id',
        'id', 'organization_id', 'number', 'customer_id', 'customer_name', 'channel', 'status', 'ordered_on', 'required_on', 'subtotal_minor', 'total_minor', 'currency', 'document_id', 'notes',
    ];

    protected $casts = [
        'ordered_on' => 'date', 'required_on' => 'date', 'subtotal_minor' => 'integer', 'total_minor' => 'integer',
    ];

    /** Who placed it. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Modules\Crm\Models\Customer::class, 'customer_id');
    }

    /**
     * The invoice raised from it.
     *
     * An order that has been billed points at its document, which is what lets
     * "has this order been invoiced?" be answered without matching on totals.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(\Modules\Invoicing\Models\Document::class, 'document_id');
    }

    public function isInvoiced(): bool
    {
        return $this->document_id !== null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Fulfillment\Models\Shipment::class, 'shipment_id');
    }
}
