<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * An account, on exactly one website.
 *
 * The password column is `password_hash` rather than Laravel's `password`
 * because the rows were imported from the Rust server and rehashing everyone
 * would have logged everyone out. `getAuthPassword()` is the one line that
 * makes the framework's guard accept that.
 */
class User extends Model implements AuthenticatableContract
{
    use \Illuminate\Auth\Authenticatable, Authorizable, HasFactory;

    /**
     * What you are to the *installation*, highest first.
     *
     * Not to be confused with your role inside an organization, which lives on
     * `organization_members` and answers a different question entirely — see
     * App\Support\Access.
     */
    public const ROLES = ['system_admin', 'owner', 'member'];

    public const ROLE_LABELS = [
        'system_admin' => 'System admin',
        'owner' => 'Organization owner',
        'member' => 'Member',
    ];

    public const ROLE_HINTS = [
        'system_admin' => 'Runs the installation. Sees every organization and every user, and decides which modules each organization may use.',
        'owner' => 'Owns one organization. Manages its team and its websites, and hands out the modules the system has granted it.',
        'member' => 'Belongs to an organization. Reaches only what their team role allows.',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'username', 'email', 'password_hash', 'role', 'active', 'profile_image',
        'website_id', 'organization_id',
        'email_verified_at', 'verification_token', 'reset_token', 'reset_sent_at',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected $casts = [
        'active' => 'boolean',
        'email_verified_at' => 'datetime',
        'reset_sent_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Both halves of the contract, not just the getter.
     *
     * The guard rehashes a password on login when the work factor has changed
     * and writes it back through this name — so returning the default here
     * would have it UPDATE a `password` column that does not exist, turning a
     * successful sign-in into a 500.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class, 'website_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** This user's seat in their organization, or null if they hold none. */
    public function membership(): ?OrganizationMember
    {
        return $this->relationLoaded('membershipRelation')
            ? $this->getRelation('membershipRelation')
            : $this->membershipRelation()->first();
    }

    public function membershipRelation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OrganizationMember::class)
            ->where('organization_id', $this->organization_id);
    }

    /**
     * The role this user holds in their organization.
     *
     * Falls back from the website role when there is no seat row yet, so a
     * user created through the website admin can still reach the modules their
     * standing implies rather than being locked out until someone seats them.
     */
    public function orgRole(): string
    {
        return $this->membership()?->role
            ?? match ($this->role) {
                'system_admin', 'owner' => 'admin',
                default => 'employee',
            };
    }

    /** Users this account may see: everyone, this organization, or just itself. */
    public function visibleUsers(): \Illuminate\Database\Eloquent\Builder
    {
        if ($this->isSystemAdmin()) {
            return self::query();
        }

        if ($this->role === 'owner') {
            return self::query()->where('organization_id', $this->organization_id);
        }

        return self::query()->whereKey($this->id);
    }

    /** Roles this account may hand out. Only a system admin can mint another. */
    public function assignableRoles(): array
    {
        return $this->isSystemAdmin() ? self::ROLES : ['member'];
    }

    /** What kind of employee this user is, for shifts and contract activities. */
    public function employeeType(): ?string
    {
        return $this->membership()?->employee_type;
    }

    public function canInModule(string $action, ?string $module = null): bool
    {
        return \App\Support\Access::can($this->orgRole(), $action, $this->employeeType(), $module);
    }

    /** Whether this user may open a given module right now. */
    public function allowedModule(string $module): bool
    {
        return (bool) $this->organization?->allowsModule($this->orgRole(), $module);
    }

    /** May this user open one named section of a module? */
    public function allowedSection(string $module, string $section): bool
    {
        return (bool) $this->organization?->allowsSection($this->orgRole(), $module, $section);
    }

    /**
     * Sites this user may administer.
     *
     * A system admin sees every site on the installation; an owner sees their
     * organization's; anyone else sees the one they are attached to.
     */
    public function websites(): \Illuminate\Database\Eloquent\Builder
    {
        if ($this->isSystemAdmin()) {
            return Website::query();
        }

        if ($this->role === 'owner') {
            return Website::query()->where('organization_id', $this->organization_id);
        }

        return Website::query()->where('id', $this->website_id);
    }

    /** Runs the installation: every organization, every user. */
    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    /**
     * Owns an organization.
     *
     * True for a system admin too — they can do everything an owner can, and
     * writing `isOwner() || isSystemAdmin()` at forty call sites is how one of
     * them ends up missing the second half.
     */
    public function isOwner(): bool
    {
        return $this->role === 'owner' || $this->isSystemAdmin();
    }

    /**
     * Reaches the website admin area.
     *
     * A member gets in when their organization seat carries a real role —
     * that is how a manager reaches the modules without also being handed the
     * public website.
     */
    public function hasAdminAccess(): bool
    {
        return $this->isOwner() || $this->orgRole() !== 'employee';
    }

    /** May administer the website's content, not merely the modules. */
    public function canEditWebsite(): bool
    {
        return $this->isOwner() || in_array($this->orgRole(), ['admin', 'manager'], true);
    }

    /**
     * Rank comparison against the ladder in ROLES.
     *
     * `$user->atLeast('admin')` is true for an owner too, which is the check
     * almost every page actually wants.
     */
    public function atLeast(string $role): bool
    {
        $mine = array_search($this->role, self::ROLES, true);
        $need = array_search($role, self::ROLES, true);

        return $mine !== false && $need !== false && $mine <= $need;
    }

    /** True when this user may act on rows belonging to $websiteId. */
    public function ownsWebsite(?string $websiteId): bool
    {
        return $this->isOwner() || ($websiteId !== null && $websiteId === $this->website_id);
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst((string) $this->role);
    }

    public function initial(): string
    {
        return strtoupper(mb_substr($this->username ?: '?', 0, 1));
    }

    public function toPublicUser(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at?->toRfc3339String(),
            'active' => $this->active,
            'profile_image' => $this->profile_image,
            'website_id' => $this->website_id,
        ];
    }
}
