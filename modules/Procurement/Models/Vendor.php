<?php

namespace Modules\Procurement\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Invoicing\Models\Item;

/**
 * Who the organization buys from.
 *
 * A vendor is the counterparty on the inbound half of the document book, and
 * the usual source of an item — which is what lets a low stock level suggest
 * who to reorder from rather than merely reporting the shortage.
 */
class Vendor extends Model
{
    use HasUuids;

    public const CATEGORIES = [
        'goods' => 'Goods',
        'services' => 'Services',
        'works' => 'Works',
        'consultancy' => 'Consultancy',
        'transport' => 'Transport',
        'other' => 'Other',
    ];

    protected $table = 'procurement_vendors';

    protected $fillable = [
        'organization_id', 'name', 'code', 'email', 'phone', 'address', 'tin',
        'category', 'lead_time_days', 'payment_terms', 'active', 'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
        'lead_time_days' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Items normally bought from this vendor. */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'vendor_id');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?: '—');
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['id' => $this->id, 'created_at' => $this->created_at?->toRfc3339String()];
    }
}
