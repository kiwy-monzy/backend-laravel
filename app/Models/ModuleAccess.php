<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (organization, role, module): may this role enter this module?
 *
 * Rows only exist where an admin has overridden the default, so an empty table
 * is a working system rather than a locked one.
 */
class ModuleAccess extends Model
{
    protected $table = 'module_access';

    // `section` is null for a whole-module rule and names a tab otherwise —
    // leaving it out of the fillable list silently dropped every section grant.
    protected $fillable = ['organization_id', 'role', 'module', 'section', 'allowed'];

    protected $casts = ['allowed' => 'boolean'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
