<?php

namespace Modules\Invoicing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The item master: one row per thing the organization sells, consumes or owns.
 *
 * **This is the spine.** An invoice line, a stock level, a purchase order and a
 * fixed asset all point at the same item, so a desktop computer is one product
 * whether it is being sold to a customer, counted in a store, ordered from a
 * supplier or issued to an employee. `role` says which of those a given item is
 * normally for; it does not restrict what can be done with it, because the same
 * computer genuinely is stock until the day it is capitalised.
 */
class Item extends Model
{
    public const TYPES = ['service' => 'Service', 'goods' => 'Goods'];

    public const ROLES = [
        'product' => 'Product (sold)',
        'material' => 'Material (consumed)',
        'asset' => 'Asset (capitalised)',
        'service' => 'Service',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'invoicing_items';

    protected $fillable = [
        'id', 'organization_id', 'name', 'sku', 'description', 'item_type', 'google_category', 'unit',
        'rate_minor', 'purchase_rate_minor', 'tax_percent',
        'track_inventory', 'stock_on_hand', 'active', 'vendor_id', 'role', 'reorder_level',
    ];

    protected $casts = [
        'rate_minor' => 'integer',
        'purchase_rate_minor' => 'integer',
        'tax_percent' => 'float',
        'stock_on_hand' => 'float',
        'track_inventory' => 'boolean',
        'active' => 'boolean',
        'reorder_level' => 'integer',
    ];

    /** Where this item is normally bought. */
    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Modules\Procurement\Models\Vendor::class, 'vendor_id');
    }

    /** Stock rows holding this item, across locations. */
    public function stock(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Inventory\Models\Stock::class, 'item_id');
    }

    /** Assets capitalised from this item. */
    public function assets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Modules\Assets\Models\Asset::class, 'item_id');
    }

    /** Quantity actually counted in the stores, across every location. */
    public function quantityOnHand(): float
    {
        return (float) $this->stock()->sum('quantity');
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ($this->role ?: '—');
    }

    /** Below its reorder level — the question the stock page exists to answer. */
    public function needsReorder(): bool
    {
        return $this->track_inventory
            && $this->reorder_level > 0
            && $this->quantityOnHand() <= $this->reorder_level;
    }

    /** The Google product-taxonomy path this item is classified under. */
    public function categoryLabel(): string
    {
        return \App\Support\Taxonomy::label(\App\Support\Taxonomy::GOOGLE, $this->google_category);
    }

    public function rate(): float
    {
        return Money::toDecimal($this->rate_minor);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $like)
            ->orWhere('sku', 'like', $like));
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'item_type' => $this->item_type,
            'unit' => $this->unit,
            'rate' => $this->rate(),
            'tax_percent' => $this->tax_percent,
            'track_inventory' => $this->track_inventory,
            'stock_on_hand' => $this->stock_on_hand,
            'active' => $this->active,
        ];
    }
}
