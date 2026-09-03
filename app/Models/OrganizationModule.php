<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A module the *system* has granted an organization.
 *
 * The top of three gates. An owner may only hand out what appears here, so
 * "which modules does FGE get" is a platform decision, not something an
 * organization can grant itself by ticking a box.
 */
class OrganizationModule extends Model
{
    protected $fillable = ['organization_id', 'module', 'granted', 'granted_by'];

    protected $casts = ['granted' => 'boolean'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
