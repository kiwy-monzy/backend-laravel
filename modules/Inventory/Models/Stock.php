<?php

namespace Modules\Inventory\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'inventory_stock';

    protected $fillable = [
        'id', 'organization_id', 'item_id', 'item_name', 'sku', 'location', 'quantity', 'reorder_level', 'unit_cost_minor', 'batch', 'expires_on', 'notes',
    ];

    protected $casts = [
        'quantity' => 'float', 'reorder_level' => 'float', 'unit_cost_minor' => 'integer', 'expires_on' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The product this quantity is of.
     *
     * `item_name` and `sku` remain as the printed label, but the item is the
     * truth: two stock rows spelled differently are still the same product if
     * they share an `item_id`, which is what makes the totals reconcile.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Invoicing\Models\Item::class, 'item_id');
    }

    /** The label to print: the linked item's name, else the stored text. */
    public function label(): string
    {
        return $this->item?->name ?? ($this->item_name ?: '—');
    }

    public function value(): int
    {
        return (int) round($this->quantity * $this->unit_cost_minor);
    }

    public function isLow(): bool
    {
        return $this->reorder_level > 0 && $this->quantity <= $this->reorder_level;
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
