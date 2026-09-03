<?php

namespace App\Support;

use App\Models\ContentSection;
use App\Models\GalleryImage;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationModule;
use App\Models\StorageCollection;
use App\Models\User;
use App\Models\Website;
use App\Services\MediaLibrary;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one place an installation is brought into existence.
 *
 * There used to be four: a provider that made the two accounts, an API
 * endpoint that seeded content, and two migrations that quietly created
 * organizations of their own. They disagreed — the provider seated the system
 * admin inside FGE, the migration moved it to Knowlia — so what you got
 * depended on the order things happened to run in. Everything is here now, and
 * the migrations only migrate.
 *
 * Two accounts, because they are two different jobs:
 *
 *   admin      — runs the installation. Its own tenant (Knowlia), every module,
 *                and no charity of its own to be confused with.
 *   fge_owner  — runs FGE. Owns the organization, its team and its website.
 *
 * Every step is idempotent: `run()` fills in whatever is missing and never
 * overwrites something a person has since edited, so it is safe on a fresh
 * database, on a half-migrated one, and on every boot after that.
 */
final class Bootstrap
{
    public const ADMIN_USERNAME = 'admin';

    public const OWNER_USERNAME = 'fge_owner';

    public const OPERATOR_SLUG = 'knowlia';

    public const TENANT_SLUG = 'fge';

    /** Guard so a long-running process does not re-check on every request. */
    private static bool $checked = false;

    /**
     * Run only when something is missing.
     *
     * The provider calls this on boot, which is every request, so the common
     * case has to be two counts and nothing else.
     */
    public static function runIfNeeded(): void
    {
        if (self::$checked) {
            return;
        }

        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('organizations')) {
                return;
            }

            // Cheap counts, one per thing `run()` is responsible for. They have
            // to cover all of it, not just the accounts: an installation set up
            // before the module seeding existed still has both users and both
            // organizations, and checking only those would mean it never
            // catches up.
            $complete = User::whereIn('username', [self::ADMIN_USERNAME, self::OWNER_USERNAME])->count() === 2
                && Organization::whereIn('slug', [self::OPERATOR_SLUG, self::TENANT_SLUG])->count() === 2
                && Website::whereKey(Website::FGE_WEBSITE_ID)->exists()
                && ContentSection::where('website_id', Website::FGE_WEBSITE_ID)->exists()
                && (! Schema::hasTable('org_departments') || \Modules\Departments\Models\Department::exists());

            if ($complete) {
                // Even a "complete" install may still carry legacy `/uploads/` URLs
                // from fixtures that pre-date the per-organization storage layout.
                // That's a cheap heal so seeded images stop 404'ing.
                try {
                    self::healLegacyUrlsIfNeeded();
                } catch (\Throwable $e) {
                    // Heal is best-effort on boot; `images:heal` covers the rest
                }
                self::$checked = true;

                return;
            }

            self::run();
            self::$checked = true;
        } catch (\Throwable $e) {
            // Mid-migration the tables exist but the columns may not yet. The
            // next boot, after `migrate` finishes, gets a clean run — failing
            // the request instead would make the migration itself unrunnable.
        }
    }

    /** Bring the installation up to date. Safe to call repeatedly. */
    public static function run(): void
    {
        $password = (string) env('BOOTSTRAP_PASSWORD', 'fgetanzania.123');

        $admin = self::user(
            self::ADMIN_USERNAME,
            (string) env('BOOTSTRAP_ADMIN_EMAIL', 'admin@knowlia.site'),
            'system_admin',
            $password,
        );

        $owner = self::user(
            self::OWNER_USERNAME,
            (string) env('BOOTSTRAP_OWNER_EMAIL', 'owner@fge.or.tz'),
            'owner',
            $password,
        );

        // The operator's own tenant. Without one, whoever runs the platform has
        // no Team page, no storage and no modules — only other people's.
        $knowlia = self::organization(self::OPERATOR_SLUG, $admin, [
            'name' => 'Knowlia',
            'email' => 'hello@knowlia.co.tz',
            'plan' => 'enterprise',
            'subscription_status' => 'active',
            'trial_ends_at' => null,
        ]);

        $fge = self::organization(self::TENANT_SLUG, $owner, [
            'name' => 'FGE',
            'email' => 'info@fge.or.tz',
            'phone' => '+255 762 060 160',
            'address' => 'Mkonze Dodoma – Tanzania',
            'plan' => 'free_trial',
            'subscription_status' => 'trialing',
            'trial_ends_at' => now()->addDays(Organization::TRIAL_DAYS),
        ]);

        self::seat($admin, $knowlia);
        self::seat($owner, $fge);

        // Knowlia gets every module: it is the reference tenant and the one the
        // operator demonstrates from. FGE gets what the system grants it, which
        // is a decision for the admin screens rather than for this file.
        self::grantAllModules($knowlia, $admin);

        $website = self::website($fge, $owner);
        self::ensureWebsiteAssets($fge->id);
        self::content($website, $fge);
        self::modules($website, $fge);
        self::healExistingContent($website, $fge);
    }

    /**
     * The same FGE content, in the module tables that own it.
     *
     * The website sections and the modules describe the same charity from two
     * directions: `team` is the people the site lists *and* the seats
     * Departments manages; `projects` is what the site advertises *and* what
     * the Projects module tracks. Seeding only the sections left every module
     * opening on an empty table, which is what `modules:check` kept reporting.
     *
     * Content stays the source: these are derived rows, created once and never
     * overwritten, so a project renamed in the module does not snap back.
     */
    private static function modules(Website $website, Organization $organization): void
    {
        $seed = self::fixture();

        self::seedDepartments($organization, $seed['team']['members'] ?? []);
        self::seedProjects($organization, $seed['projects']['items'] ?? []);
        self::seedAppointments($organization, $seed['events']['items'] ?? []);
        self::seedUploads($website, $organization, $seed['gallery']['images'] ?? []);
    }

    /**
     * The team's groupings become departments, and each person a seat.
     *
     * A seat without a `user_id` is a person on the team who has no login —
     * which is most of a charity's board, and what `allow_seats_without_a_login`
     * made room for.
     */
    private static function seedDepartments(Organization $organization, array $members): void
    {
        if (! Schema::hasTable('org_departments') || $members === []) {
            return;
        }

        $groups = [];
        foreach ($members as $member) {
            $groups[$member['category'] ?: 'Board'][] = $member;
        }

        foreach ($groups as $name => $people) {
            $department = \Modules\Departments\Models\Department::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $name],
                [
                    'id' => (string) Str::uuid(),
                    'code' => Str::upper(Str::substr(Str::slug($name), 0, 6)),
                    'head' => $people[0]['name'] ?? null,
                    'active' => true,
                ],
            );

            foreach ($people as $position => $person) {
                OrganizationMember::firstOrCreate(
                    ['organization_id' => $organization->id, 'person_name' => $person['name']],
                    [
                        'role' => 'employee',
                        'active' => true,
                        'department_id' => $department->id,
                        'collection' => Str::lower($name),
                        'job_title' => $person['role'] ?? null,
                        'public_title' => $person['role'] ?? null,
                        'photo_url' => $person['image'] ?: null,
                        'show_on_website' => true,
                        'position' => $position,
                    ],
                );
            }
        }
    }

    private static function seedProjects(Organization $organization, array $items): void
    {
        if (! Schema::hasTable('projects_records')) {
            return;
        }

        foreach ($items as $item) {
            \Modules\Projects\Models\Project::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $item['title']],
                [
                    'id' => $item['id'] ?? (string) Str::uuid(),
                    'code' => Str::upper(Str::substr(Str::slug($item['title']), 0, 8)),
                    // The site's vocabulary is "ongoing"; the module's is "active".
                    'status' => ($item['status'] ?? 'ongoing') === 'ongoing' ? 'active' : 'completed',
                    'billing_method' => 'non_billable',
                    'description' => $item['description'] ?? null,
                ],
            );
        }
    }

    /** The site's events are the bookings module's appointments. */
    private static function seedAppointments(Organization $organization, array $items): void
    {
        if (! Schema::hasTable('bookings_appointments')) {
            return;
        }

        foreach ($items as $item) {
            $startsAt = self::eventStart($item);

            \Modules\Bookings\Models\Appointment::firstOrCreate(
                ['organization_id' => $organization->id, 'service' => $item['title']],
                [
                    'id' => (string) Str::uuid(),
                    'customer' => $organization->name,
                    'status' => $startsAt && $startsAt->isPast() ? 'completed' : 'booked',
                    'starts_at' => $startsAt,
                    'duration_minutes' => 120,
                    'location' => $item['location'] ?? null,
                    'notes' => $item['description'] ?? null,
                ],
            );
        }
    }

    /** `2025-02-15` + `10:00 AM`, or midnight when the time will not parse. */
    private static function eventStart(array $event): ?\Illuminate\Support\Carbon
    {
        $date = $event['date'] ?? null;

        if (! $date) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse(trim($date . ' ' . ($event['time'] ?? '')));
        } catch (\Throwable $e) {
            try {
                return \Illuminate\Support\Carbon::parse($date);
            } catch (\Throwable $e) {
                return null;
            }
        }
    }

    /**
     * Gallery pictures are files, so Storage should know about them.
     *
     * They land in the organization's `website` collection — the one
     * StorageCollection::seedFor describes as "images used on the public site".
     */
    private static function seedUploads(Website $website, Organization $organization, array $images): void
    {
        if (! Schema::hasTable('uploads') || $images === []) {
            return;
        }

        $collection = StorageCollection::where('organization_id', $organization->id)
            ->where('slug', 'website')
            ->first();

        foreach ($images as $image) {
            $rawUrl = $image['url'] ?? '';

            if ($rawUrl === '' || Str::startsWith($rawUrl, 'data:')) {
                continue;
            }

            $url = self::storageUrlForLegacy($rawUrl, $organization->id);

            // Ensure the file actually exists for the new URL (adopt if needed)
            $rel = ltrim(Str::after($url, '/storage/'), '/');
            $size = 0;
            try {
                if (Storage::disk('public')->exists($rel)) {
                    $size = Storage::disk('public')->size($rel);
                }
            } catch (\Throwable $e) {}

            $existing = \App\Models\Upload::where('url', $rawUrl)->first() ?: \App\Models\Upload::where('url', $url)->first();
            if ($existing) {
                // Heal legacy URL pointing at old path
                if ($existing->url !== $url) {
                    $existing->forceFill([
                        'url' => $url,
                        'path' => $rel,
                        'organization_id' => $organization->id,
                        'collection_id' => $collection?->id,
                        'size' => $size ?: $existing->size,
                    ])->save();
                }
                continue;
            }

            \App\Models\Upload::firstOrCreate(
                ['url' => $url],
                [
                    'id' => (string) Str::uuid(),
                    'website_id' => $website->id,
                    'organization_id' => $organization->id,
                    'collection_id' => $collection?->id,
                    'filename' => basename(parse_url($url, PHP_URL_PATH) ?: $url),
                    'mime' => self::mimeFor($url),
                    'size' => $size,
                    'path' => $rel,
                ],
            );
        }
    }

    private static function mimeFor(string $url): string
    {
        return match (Str::lower(pathinfo(parse_url($url, PHP_URL_PATH) ?: $url, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };
    }

    /**
     * An account with the role it is meant to hold.
     *
     * A pre-existing account keeps its password — this is not a way to reset
     * one — but its role and organization are corrected, because those are what
     * the old split bootstrap got wrong.
     */
    private static function user(string $username, string $email, string $role, string $password): User
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            return User::create([
                'id' => (string) Str::uuid(),
                'username' => $username,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'role' => $role,
                'active' => true,
                'profile_image' => null,
            ]);
        }

        if ($user->role !== $role) {
            $user->update(['role' => $role]);
        }

        return $user;
    }

    /** @param array<string,mixed> $attributes */
    private static function organization(string $slug, User $owner, array $attributes): Organization
    {
        $organization = Organization::where('slug', $slug)->first();

        if (! $organization) {
            $organization = Organization::create($attributes + [
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'owner_id' => $owner->id,
                'country' => 'TZ',
                'currency' => 'TZS',
            ]);
        } elseif ($organization->owner_id !== $owner->id) {
            $organization->update(['owner_id' => $owner->id]);
        }

        if ($owner->organization_id !== $organization->id) {
            $owner->update(['organization_id' => $organization->id]);
        }

        StorageCollection::seedFor($organization);

        return $organization;
    }

    /** The owner administers their own organization. */
    private static function seat(User $user, Organization $organization): void
    {
        OrganizationMember::firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => 'admin', 'active' => true],
        );
    }

    private static function grantAllModules(Organization $organization, User $grantedBy): void
    {
        foreach (Modules::slugs() as $module) {
            OrganizationModule::firstOrCreate(
                ['organization_id' => $organization->id, 'module' => $module],
                ['granted' => true, 'granted_by' => $grantedBy->id],
            );
        }
    }

    private static function website(Organization $organization, User $owner): Website
    {
        $website = Website::firstOrCreate(
            ['id' => Website::FGE_WEBSITE_ID],
            [
                'owner_id' => $owner->id,
                'organization_id' => $organization->id,
                'name' => 'FGE',
                'slug' => 'fge',
                'domain' => 'fge.or.tz',
                'is_active' => true,
                'template' => 'template0',
                'theme' => 'fge-custom',
            ],
        );

        if ($owner->website_id !== $website->id) {
            $owner->update(['website_id' => $website->id]);
        }

        return $website;
    }

    /**
     * Ensure every image referenced by the fixture actually exists on disk for this organization.
     *
     * The bundled assets live in `database/seeders/fixtures/assets/website/` (tracked in git) and
     * legacy local copies may sit in `storage/app/public/uploads/_shared/website/`. Either is adopted
     * into `uploads/{organizationId}/website/` so the seeded URLs point at a file that the
     * `public/storage` symlink can serve.
     */
    private static function ensureWebsiteAssets(string $organizationId): void
    {
        $collection = 'website';
        $disk = Storage::disk('public');

        // Gather basenames fixture wants
        $seed = self::fixture();
        $wanted = [];

        $collect = function (mixed $v) use (&$wanted, &$collect) {
            if (is_string($v) && (str_starts_with($v, '/uploads/') || str_starts_with($v, '/storage/'))) {
                $wanted[basename(parse_url($v, PHP_URL_PATH) ?: $v)] = $v;
            } elseif (is_array($v)) {
                foreach ($v as $item) $collect($item);
            }
        };
        $collect($seed);

        foreach (array_keys($wanted) as $basename) {
            if ($basename === '' || $basename === 'uploads' || $basename === 'storage') continue;

            $target = 'uploads/'.$organizationId.'/'.$collection.'/'.$basename;
            if ($disk->exists($target)) continue;

            $source = self::findSourceFile($basename, $collection);
            if ($source && is_file($source)) {
                try {
                    $media = app(MediaLibrary::class);
                    $media->adopt($source, $organizationId, $collection);
                } catch (\Throwable $e) {
                    // Fallback to direct copy if MediaLibrary fails
                    $disk->put($target, file_get_contents($source));
                }
            }
        }
    }

    private static function findSourceFile(string $basename, string $collection = 'website'): ?string
    {
        $candidates = [
            database_path('seeders/fixtures/assets/'.$collection.'/'.$basename),
            storage_path('app/public/uploads/_shared/'.$collection.'/'.$basename),
            storage_path('app/public/uploads/'.$collection.'/'.$basename),
        ];

        foreach ($candidates as $c) {
            if (is_file($c)) return $c;
        }

        // Search recursively under storage/app/public/uploads and fixtures/assets
        foreach ([storage_path('app/public/uploads'), database_path('seeders/fixtures/assets')] as $root) {
            if (! is_dir($root)) continue;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getBasename() === $basename) {
                    return $file->getPathname();
                }
            }
        }

        return null;
    }

    private static function storageUrlForLegacy(string $url, string $organizationId, string $collection = 'website'): string
    {
        if ($url === '' || str_starts_with($url, 'data:')) return $url;
        if (! str_starts_with($url, '/uploads/') && ! str_starts_with($url, '/storage/')) return $url;

        $basename = basename(parse_url($url, PHP_URL_PATH) ?: $url);
        if ($basename === '' || $basename === 'uploads' || $basename === 'storage') return $url;

        // If already /storage/... and file exists at that exact location, keep it
        if (str_starts_with($url, '/storage/')) {
            $rel = ltrim(Str::after($url, '/storage/'), '/');
            if (Storage::disk('public')->exists($rel)) return $url;
        }

        // Preferred target for this organization
        $target = 'uploads/'.$organizationId.'/'.$collection.'/'.$basename;
        if (Storage::disk('public')->exists($target)) {
            return '/storage/'.$target;
        }

        // Try to materialise from source
        $source = self::findSourceFile($basename, $collection);
        if ($source && is_file($source)) {
            try {
                $media = app(MediaLibrary::class);
                $adopted = $media->adopt($source, $organizationId, $collection);
                if ($adopted) return $adopted;
            } catch (\Throwable $e) {}
        }

        // Fallback to _shared URL if that file exists (readable even without per-org copy)
        $shared = 'uploads/_shared/'.$collection.'/'.$basename;
        if (Storage::disk('public')->exists($shared)) {
            return '/storage/'.$shared;
        }

        // Last resort: if shared file exists on the original local disk (not yet copied to public disk's _shared), adopt it
        $sharedSource = storage_path('app/public/uploads/_shared/'.$collection.'/'.$basename);
        if (is_file($sharedSource)) {
            Storage::disk('public')->put($shared, file_get_contents($sharedSource));
            return '/storage/'.$shared;
        }

        // If bundled asset exists, copy to shared so fallback works
        $bundled = database_path('seeders/fixtures/assets/'.$collection.'/'.$basename);
        if (is_file($bundled)) {
            Storage::disk('public')->put($shared, file_get_contents($bundled));
            // Also adopt to org
            try {
                $media = app(MediaLibrary::class);
                $adopted = $media->adopt($bundled, $organizationId, $collection);
                if ($adopted) return $adopted;
            } catch (\Throwable $e) {}
            return '/storage/'.$shared;
        }

        // Give up – return storage path for org (will be healed once file is deployed)
        return '/storage/'.$target;
    }

    private static function normalizeUrls(mixed $value, string $organizationId): mixed
    {
        if (is_string($value) && (str_starts_with($value, '/uploads/') || str_starts_with($value, '/storage/'))) {
            return self::storageUrlForLegacy($value, $organizationId);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) $value[$k] = self::normalizeUrls($v, $organizationId);
            return $value;
        }
        return $value;
    }

    private static function healExistingContent(Website $website, Organization $organization): void
    {
        $orgId = $organization->id;

        foreach (ContentSection::where('website_id', $website->id)->get() as $row) {
            $orig = $row->data;
            if (! is_array($orig)) continue;
            $fixed = self::normalizeUrls($orig, $orgId);
            // Only save if URLs changed or contained legacy prefix
            if ($fixed !== $orig) {
                $row->forceFill(['data' => $fixed])->save();
            } else {
                // Also heal if data JSON string still contains /uploads/ that didn't get normalized due to not being pure prefix
                $enc = json_encode($orig);
                if (str_contains($enc, '/uploads/')) {
                    $healed = self::normalizeUrls($orig, $orgId);
                    if ($healed !== $orig) $row->forceFill(['data'=>$healed])->save();
                }
            }
        }

        foreach (GalleryImage::where('website_id', $website->id)->get() as $img) {
            $new = self::storageUrlForLegacy((string) $img->url, $orgId);
            if ($new !== $img->url) $img->forceFill(['url'=>$new])->save();
        }

        // Heal direct Upload rows that still point at legacy
        foreach (\App\Models\Upload::where('organization_id', $orgId)->get() as $up) {
            $new = self::storageUrlForLegacy((string) $up->url, $orgId);
            if ($new !== $up->url) {
                $up->forceFill([
                    'url' => $new,
                    'path' => ltrim(Str::after($new, '/storage/'), '/'),
                ])->save();
            }
        }

        // Heal organization general logo
        if (is_array($organization->general)) {
            $fixedGeneral = self::normalizeUrls($organization->general, $orgId);
            if ($fixedGeneral !== $organization->general) {
                $organization->forceFill(['general'=>$fixedGeneral])->save();
            }
        }
    }

    private static function healLegacyUrlsIfNeeded(): void
    {
        if (! Schema::hasTable('content_sections') || ! Schema::hasTable('gallery_images')) {
            return;
        }

        $hasLegacy = ContentSection::where('data', 'like', '%/uploads/%')->exists()
            || GalleryImage::where('url', 'like', '%/uploads/%')->exists()
            || (Schema::hasTable('uploads') && \App\Models\Upload::where('url', 'like', '%/uploads/%')->exists())
            || (Schema::hasTable('content_sections') && ContentSection::where('data', 'like', '%"\/uploads\/%')->exists());

        if (! $hasLegacy) {
            return;
        }

        $fge = Organization::where('slug', self::TENANT_SLUG)->first();
        $website = Website::whereKey(Website::FGE_WEBSITE_ID)->first();
        if (! $fge || ! $website) return;

        self::ensureWebsiteAssets($fge->id);
        self::healExistingContent($website, $fge);
    }

    /**
     * The site's starting content, from the fixture.
     *
     * `general` — identity, contact, social links, visibility — is the
     * organization's profile rather than a content row, so it is merged into
     * the profile and the profile's own values win: a charity that has already
     * edited its address does not get the fixture's back.
     */
    private static function content(Website $website, Organization $organization): void
    {
        $seed = self::fixture();

        if (isset($seed['general'])) {
            $normalizedGeneral = self::normalizeUrls($seed['general'], $organization->id);
            $organization->update([
                'general' => array_replace_recursive($normalizedGeneral, $organization->general ?? []),
            ]);
        }

        $locale = $website->default_language ?: 'en';

        foreach ($seed as $section => $data) {
            if ($section === 'general' || ! in_array($section, Website::SECTIONS, true)) {
                continue;
            }

            $normalized = self::normalizeUrls($data, $organization->id);

            ContentSection::firstOrCreate(
                ['website_id' => $website->id, 'section' => $section, 'locale' => $locale],
                ['data' => $normalized],
            );
        }

        foreach ($seed['gallery']['images'] ?? [] as $image) {
            if (($image['id'] ?? '') === '') {
                continue;
            }

            $url = self::storageUrlForLegacy($image['url'] ?? '', $organization->id);

            GalleryImage::firstOrCreate(
                ['id' => (string) $image['id']],
                [
                    'website_id' => $website->id,
                    'url' => $url,
                    'caption' => $image['caption'] ?? '',
                    'disabled' => $image['disabled'] ?? false,
                ],
            );
        }
    }

    /**
     * The starting content, carried over from the Rust server's `site_content`
     * table. Kept as data in `database/seeders/fixtures/` rather than a string
     * constant in a controller, which is where half of it used to live.
     *
     * @return array<string,mixed>
     */
    private static function fixture(): array
    {
        $path = database_path('seeders/fixtures/fge_content.json');

        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data) ? $data : [];
    }
}
