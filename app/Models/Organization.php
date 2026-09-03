<?php

namespace App\Models;

use App\Support\Access;
use App\Support\Modules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant: a charity or company, its people, its websites and its plan.
 */
class Organization extends Model
{
    use \Modules\Zones\Models\Concerns\HasZones;

    /** Where this organization trades. */
    public function zoneRole(): string
    {
        return 'operating';
    }

    /**
     * Plans, ported from `knowlia-invoice/src/subscription.rs`.
     *
     * Deliberately no module list: which modules a plan covers is derived from
     * each module's own `requires_plan` via `planIncludes()`. A roster here as
     * well would be a second source of truth, and it drifted the last time
     * modules were added.
     */
    public const PLANS = [
        'free_trial' => [
            'label' => 'Free Trial',
            'tagline' => 'Every module, free for 14 days. No card needed.',
            'price_minor' => 0,
        ],
        'starter' => [
            'label' => 'Starter',
            'tagline' => 'Invoicing and expenses for a small team.',
            'price_minor' => 900,
        ],
        'professional' => [
            'label' => 'Professional',
            'tagline' => 'Adds inventory, bookings, procurement and billing.',
            'price_minor' => 2900,
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'tagline' => 'Every module, unlimited seats and departments.',
            'price_minor' => 7900,
        ],
    ];

    public const TRIAL_DAYS = 14;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'slug', 'owner_id', 'email', 'phone', 'address',
        'country', 'currency', 'logo_url', 'general',
        'template', 'theme', 'theme_overrides',
        'plan', 'subscription_status', 'trial_ends_at', 'renews_at',
    ];

    protected $casts = [
        'general' => 'array',
        'theme_overrides' => 'array',
        'trial_ends_at' => 'datetime',
        'renews_at' => 'datetime',
    ];

    /**
     * The website-facing identity: site name, logo, contact, social links and
     * per-section visibility. This is what the public API and every template
     * render as the `general` section, rather than a per-website content row.
     */
    public function profileGeneral(): array
    {
        return $this->general ?? [];
    }

    /**
     * The layout the organization's websites render in.
     *
     * `null` means "not chosen yet" — the website falls back to its own
     * column, then to the default template.
     */
    public function profileTemplate(): ?string
    {
        return $this->template ?: null;
    }

    /** The palette the organization's websites render in, or `fge` (emerald). */
    public function profileTheme(): string
    {
        return $this->theme ?: 'fge';
    }

    /** Hand-picked colour tweaks on top of the chosen palette. */
    public function profileThemeOverrides(): array
    {
        return $this->theme_overrides ?: [];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function moduleAccess(): HasMany
    {
        return $this->hasMany(ModuleAccess::class);
    }

    public function moduleGrants(): HasMany
    {
        return $this->hasMany(OrganizationModule::class);
    }

    /**
     * Whether the system has granted this organization a module at all.
     *
     * An organization with no grant rows at all is treated as fully granted —
     * that is a brand-new tenant nobody has configured yet, and locking it out
     * of everything until a system admin visits would be a worse default than
     * the plan already provides.
     */
    public function isGranted(string $module): bool
    {
        static $cache = [];

        if (! array_key_exists($this->id, $cache)) {
            $cache[$this->id] = $this->moduleGrants()->pluck('granted', 'module')->all();
        }

        if ($cache[$this->id] === []) {
            return true;
        }

        return (bool) ($cache[$this->id][$module] ?? false);
    }

    public function planLabel(): string
    {
        return self::PLANS[$this->plan]['label'] ?? ucfirst($this->plan);
    }

    /** Days left on the trial; negative once it has lapsed. */
    public function trialDaysLeft(): ?int
    {
        return $this->trial_ends_at ? (int) now()->diffInDays($this->trial_ends_at, false) : null;
    }

    public function onTrial(): bool
    {
        return $this->plan === 'free_trial' && ($this->trialDaysLeft() ?? -1) >= 0;
    }

    /**
     * Whether the subscription entitles this org to write at all.
     *
     * A lapsed trial stays readable — the data is theirs — but writes are
     * refused, which is the same shape the reference products use.
     */
    public function isActive(): bool
    {
        if ($this->plan === 'free_trial') {
            return $this->onTrial();
        }

        return in_array($this->subscription_status, ['active', 'trialing'], true);
    }

    /**
     * Whether the plan includes a module at all, before role permissions apply.
     *
     * **Derived from the module's own `requires_plan`, not from a list here.**
     * There used to be a hard-coded roster per plan *and* a `requires_plan` in
     * every `module.json`, which is two sources of truth for one question —
     * and they drifted the moment ten modules were added: Storage and Contact
     * declare "any plan" yet were absent from the Professional roster, so a
     * Professional organization was refused modules it was entitled to.
     *
     * Now a plan is just a rank, and a module is included when its own
     * requirement sits at or below it. Adding a module needs no edit here.
     */
    public function planIncludes(string $module): bool
    {
        if (in_array($module, Access::ALWAYS_AVAILABLE, true)) {
            return true;
        }

        $required = Modules::requiresPlan($module);

        if ($required === null) {
            return true;
        }

        return self::planRank($this->plan) >= self::planRank($required);
    }

    /**
     * How much a plan unlocks. The trial is deliberately top-rank: it exists to
     * show the whole product.
     */
    public static function planRank(string $plan): int
    {
        return match ($plan) {
            'starter' => 1,
            'professional' => 2,
            'enterprise', 'free_trial' => 3,
            default => 0,
        };
    }

    /** The modules a plan covers, computed from the registry. */
    public static function planModules(string $plan): array
    {
        return array_values(array_filter(
            Modules::slugs(),
            fn (string $slug) => self::planRank($plan) >= self::planRank(Modules::requiresPlan($slug) ?? 'free'),
        ));
    }

    /**
     * The final gate: plan, then this organization's role matrix.
     *
     * Falls back to `Access::moduleAllowedByDefault()` when the org has never
     * touched the matrix, so a new organization works without anyone having to
     * configure twenty toggles first.
     */
    public function allowsModule(string $role, string $module): bool
    {
        // Three gates, narrowest last: does the module exist, has the system
        // granted it to this organization, does the plan cover it, and only
        // then does the owner's role matrix get a say.
        if (! Modules::exists($module) || ! $this->isGranted($module) || ! $this->planIncludes($module)) {
            return false;
        }

        $row = $this->moduleAccess()
            ->where('role', $role)
            ->where('module', $module)
            ->whereNull('section')
            ->first();

        $allowed = $row ? (bool) $row->allowed : Access::moduleAllowedByDefault($role, $module);

        // A role refused the module generally may still hold one section of it,
        // and must be able to reach the module to get there — the rail would
        // otherwise hide the only tab they have.
        if (! $allowed) {
            return $this->moduleAccess()
                ->where('role', $role)
                ->where('module', $module)
                ->whereNotNull('section')
                ->where('allowed', true)
                ->exists();
        }

        return true;
    }

    /**
     * May this role open one particular section of a module?
     *
     * An explicit row wins. Otherwise the section inherits the module: someone
     * who may enter Inventory sees all of its tabs unless a tab is singled out,
     * which keeps the common case free of configuration.
     */
    public function allowsSection(string $role, string $module, string $section): bool
    {
        $row = $this->moduleAccess()
            ->where('role', $role)
            ->where('module', $module)
            ->where('section', $section)
            ->first();

        if ($row) {
            return (bool) $row->allowed;
        }

        $moduleRow = $this->moduleAccess()
            ->where('role', $role)
            ->where('module', $module)
            ->whereNull('section')
            ->first();

        return $moduleRow
            ? (bool) $moduleRow->allowed
            : Access::moduleAllowedByDefault($role, $module);
    }

    /** Every module this role may enter, in registry order. */
    public function modulesFor(string $role): array
    {
        return array_values(array_filter(
            Modules::slugs(),
            fn (string $slug) => $this->allowsModule($role, $slug),
        ));
    }
}
