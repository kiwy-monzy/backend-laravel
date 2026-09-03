<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A named folder inside one organization's storage, with a permission on it.
 *
 * The `website` collection feeds the public site's image pickers; `invoices`
 * holds proof-of-payment; an owner can add whatever else they need ("Team
 * documents", "Grant reports"). Because a collection is a real directory —
 * `uploads/{organization_id}/{slug}` — "back up this organization" and "how
 * much is this organization using" are both answerable from the disk.
 */
class StorageCollection extends Model
{
    /** Collections the app creates and relies on by slug. */
    public const SYSTEM = ['website', 'invoices', 'documents'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'organization_id', 'name', 'slug', 'description',
        'min_role', 'is_system', 'selectable',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'selectable' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class, 'collection_id');
    }

    /** The directory on the public disk, relative to its root. */
    public function path(): string
    {
        return 'uploads/' . $this->organization_id . '/' . $this->slug;
    }

    /** Whether a team role may add or remove files here. */
    public function writableBy(string $role): bool
    {
        return Access::can($role, 'add') && Access::atLeast($role, $this->min_role);
    }

    public function bytes(): int
    {
        return (int) $this->uploads()->sum('size');
    }

    /**
     * Create the standard three for a new organization.
     *
     * Called when an organization is created rather than lazily, so an owner
     * opening Storage on day one sees somewhere to put things instead of an
     * empty page with a "create a collection" prompt.
     */
    public static function seedFor(Organization $organization): void
    {
        foreach ([
            ['website', 'Website', 'Images used on the public site — hero, projects, team, gallery.', 'salesperson'],
            ['invoices', 'Invoices', 'Proof of payment and documents attached to invoices.', 'employee'],
            ['documents', 'Documents', 'General organization files.', 'employee'],
        ] as [$slug, $name, $description, $minRole]) {
            static::firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => $slug],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'description' => $description,
                    'min_role' => $minRole,
                    'is_system' => true,
                    'selectable' => true,
                ],
            );
        }
    }

    /** Total bytes an organization is using, measured on disk. */
    public static function organizationBytes(string $organizationId): int
    {
        $disk = Storage::disk('public');
        $root = 'uploads/' . $organizationId;

        if (! $disk->exists($root)) {
            return 0;
        }

        $bytes = 0;
        foreach ($disk->allFiles($root) as $file) {
            $bytes += $disk->size($file);
        }

        return $bytes;
    }
}
