<?php

namespace Modules\Departments\Models;

use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'org_departments';

    protected $fillable = [
        'id', 'organization_id', 'name', 'code', 'head', 'cost_centre', 'budget_minor', 'active', 'notes',
    ];

    protected $casts = [
        'budget_minor' => 'integer', 'active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The people in this department.
     *
     * A department used to be a name with a budget and nobody in it; this is
     * what makes "who is in Finance" answerable, and it is the same seat the
     * team page and the website team section read.
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class, 'department_id');
    }

    /** Assets charged to this department. */
    public function assets(): HasMany
    {
        return $this->hasMany(\Modules\Assets\Models\Asset::class, 'department_id');
    }

    public function headcount(): int
    {
        return $this->members()->where('active', true)->count();
    }

    public function toApi(): array
    {
        return $this->only($this->fillable) + ['created_at' => $this->created_at?->toRfc3339String()];
    }
}
