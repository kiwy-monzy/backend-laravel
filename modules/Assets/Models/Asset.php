<?php

namespace Modules\Assets\Models;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    public const CATEGORIES = ['equipment' => 'Equipment', 'computer' => 'Computer', 'furniture' => 'Furniture', 'vehicle' => 'Vehicle', 'tool' => 'Tool', 'building' => 'Building', 'other' => 'Other'];

    public const STATUSES = ['in_use' => 'In use', 'in_store' => 'In store', 'under_repair' => 'Under repair', 'retired' => 'Retired', 'lost' => 'Lost', 'sold' => 'Sold'];

    public const CONDITIONS = ['new' => 'New', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'assets_records';

    protected $fillable = [
        'id', 'organization_id', 'tag', 'name', 'category', 'serial_number', 'item_id', 'assigned_to', 'department', 'location', 'status', 'condition', 'purchased_on', 'purchase_cost_minor', 'current_value_minor', 'useful_life_years', 'warranty_until', 'notes',
        'department_id', 'assigned_user_id', 'vendor_id',
    ];

    protected $casts = [
        'purchased_on' => 'date', 'purchase_cost_minor' => 'integer', 'current_value_minor' => 'integer', 'useful_life_years' => 'integer', 'warranty_until' => 'date',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The product this asset was capitalised from.
     *
     * A desktop computer is one item; buying ten of them makes ten assets that
     * all point back at it, which is how the same thing can be sold from the
     * catalogue and issued to an employee without being two records.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(\Modules\Invoicing\Models\Item::class, 'item_id');
    }

    public function departmentRef(): BelongsTo
    {
        return $this->belongsTo(\Modules\Departments\Models\Department::class, 'department_id');
    }

    /** The seat holding it — a real person on the team, not a typed name. */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\OrganizationMember::class, 'assigned_user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\Modules\Procurement\Models\Vendor::class, 'vendor_id');
    }

    /** Who holds it: the linked seat, else the typed name. */
    public function holderLabel(): string
    {
        return $this->holder?->displayName() ?: ($this->assigned_to ?: '—');
    }

    public function departmentLabel(): string
    {
        return $this->departmentRef?->name ?: ($this->department ?: '—');
    }

    /**
     * Straight-line book value for today.
     *
     * Falls back to the recorded current value when there is no life to
     * depreciate over, so an asset never silently reads as worthless.
     */
    public function bookValue(): int
    {
        if (! $this->purchased_on || ! $this->useful_life_years || $this->purchase_cost_minor <= 0) {
            return (int) $this->current_value_minor;
        }

        $years = $this->purchased_on->diffInDays(now()) / 365.25;
        $remaining = max(0.0, 1.0 - ($years / $this->useful_life_years));

        return (int) round($this->purchase_cost_minor * $remaining);
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
