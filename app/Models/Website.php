<?php

namespace App\Models;

use App\Support\Templates;
use App\Support\ThemeFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Projects\Models\Project;
use Modules\Bookings\Models\Appointment;

class Website extends Model
{
    public const FGE_WEBSITE_ID = '526122f2-a101-44d5-bca0-9d6de7bf9af6';

    public const SECTIONS = [
        'general', 'hero', 'about', 'projects', 'team', 'gallery',
        'blog', 'events', 'theme', 'chatbot', 'donate',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'owner_id', 'organization_id', 'name', 'slug', 'domain', 'is_active',
        'template', 'theme', 'theme_overrides',
        'splash', 'splash_seconds', 'splash_tagline',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'robots', 'og_image', 'og_type', 'twitter_card', 'twitter_site',
        'default_language', 'languages',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'theme_overrides' => 'array',
        'languages' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'website_id', 'id');
    }

    /**
     * Always a template that exists, so a bad column value cannot 500 the public site.
     *
     * The look lives on the organization profile now: a website renders in its
     * organization's template unless it holds one of its own (previews set the
     * attribute directly on the loaded site, which is why the site column
     * still outranks the profile when set).
     */
    public function templateKey(): string
    {
        return Templates::resolve($this->template ?: $this->organization?->template);
    }

    /** The palette to render in: an explicit site choice, else the profile's. */
    public function effectiveTheme(): string
    {
        return $this->theme ?: ($this->organization?->theme ?: 'fge');
    }

    /** Colour tweaks: an explicit site set, else the profile's. */
    public function effectiveThemeOverrides(): array
    {
        return $this->theme_overrides ?: ($this->organization?->theme_overrides ?: []);
    }

    public function themeCss(): string
    {
        return ThemeFactory::css($this);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ContentSection::class, 'website_id', 'id');
    }

    public function galleryImages(): HasMany
    {
        return $this->hasMany(GalleryImage::class, 'website_id', 'id');
    }

    public function sectionData(string $section, ?string $locale = null): ?array
    {
        $locale = $locale ?: $this->default_language ?: 'en';
        $fallback = $this->default_language ?: 'en';

        return $this->sections()->where('section', $section)->where('locale', $locale)->value('data')
            ?? $this->sections()->where('section', $section)->where('locale', $fallback)->value('data');
    }

    /** The locales this site actually offers — always at least its default. */
    public function offeredLanguages(): array
    {
        $langs = collect($this->languages ?: [])->filter()->values()->all();
        $default = $this->default_language ?: 'en';

        return array_values(array_unique(array_merge([$default], $langs)));
    }

    /**
     * The full site payload for a locale, with per-section fallback.
     *
     * Same shape the frontend consumes: the 11 content sections merged with
     * the live gallery rows.
     *
     * A section that has no row in the requested locale falls back to the
     * default language's, so a site translated for its hero but not its footer
     * still renders a complete page rather than a gap where the untranslated
     * section was.
     */
    public function siteData(?string $locale = null): array
    {
        $locale = $locale ?: $this->default_language ?: 'en';
        $fallback = $this->default_language ?: 'en';

        $data = [];
        foreach (self::SECTIONS as $section) {
            $row = $this->sections()->where('section', $section)->where('locale', $locale)->first()
                ?? $this->sections()->where('section', $section)->where('locale', $fallback)->first();
            if ($row) {
                $data[$section] = $row->data;
            }
        }

        // The `general` section — identity, contact, social links, visibility —
        // is the organization's profile, not a per-website content row. The
        // profile wins field by field; anything it does not carry yet falls
        // back to the stored section, so an org without a profile renders
        // exactly as it did before.
        $profile = $this->organization?->general;
        if (is_array($profile)) {
            $data['general'] = array_replace_recursive($data['general'] ?? [], $profile);
        }

        $images = $this->galleryImages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (GalleryImage $g) => $this->imageToApi($g))
            ->values()
            ->all();

        if ($images !== []) {
            $data['gallery'] = ['images' => $images];
        }

        // The team is the organization's team — the same seats the admin's Team
        // page manages — not a second list of the same people kept in content.
        // It overrides the stored section for exactly the reason the gallery
        // does: when a live record exists, an edited copy of it is a stale copy.
        // Nothing is invented here; a site with nobody published keeps whatever
        // its content already said.
        $team = $this->publishedTeam();

        if ($team !== []) {
            $data['team'] = ['members' => $team];
        }

        $projects = $this->publishedProjects();

        if ($projects !== []) {
            $data['projects'] = ['items' => $projects];
        }

        $events = $this->publishedEvents();

        if ($events !== []) {
            $data['events'] = ['items' => $events];
        }

        return $data;
    }

    /**
     * Organization seats marked for the public site, shaped like content rows.
     *
     * `category` is the seat's collection, which is what the team template
     * groups by — Board, Management, IT and so on.
     *
     * @return array<int,array{name:string,role:string,category:string,image:?string}>
     */
    public function publishedTeam(): array
    {
        if (! $this->organization_id) {
            return [];
        }

        return OrganizationMember::query()
            ->where('organization_id', $this->organization_id)
            ->where('show_on_website', true)
            ->where('active', true)
            ->with('user')
            ->orderBy('position')
            ->get()
            ->map(fn (OrganizationMember $m) => [
                'name' => $m->displayName(),
                'role' => $m->displayTitle(),
                'category' => $m->collectionLabel(),
                'image' => $m->photo_url,
            ])
            ->filter(fn (array $row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * Active projects from the Projects module, shaped like content rows.
     *
     * Only projects with status "active" are included. The website's
     * content section remains the fallback when no live projects exist.
     *
     * @return array<int,array{title:string,subtitle:string,description:string,image:?string,status:string}>
     */
    public function publishedProjects(): array
    {
        if (! $this->organization_id) {
            return [];
        }

        return Project::query()
            ->where('organization_id', $this->organization_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $p) => [
                'title' => $p->name,
                'subtitle' => $p->code ?? '',
                'description' => $p->description ?? '',
                'image' => null,
                'status' => 'active',
            ])
            ->filter(fn (array $row) => $row['title'] !== '')
            ->values()
            ->all();
    }

    /**
     * Upcoming appointments from the Bookings module, shaped like content rows.
     *
     * Only non-cancelled appointments with a future start are included.
     * The website's content section remains the fallback when no live
     * events exist.
     *
     * @return array<int,array{title:string,description:string,date:string,time:string,location:string,city:string,category:string,image_url:?string}>
     */
    public function publishedEvents(): array
    {
        if (! $this->organization_id) {
            return [];
        }

        return Appointment::query()
            ->where('organization_id', $this->organization_id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Appointment $a) => [
                'title' => $a->service ?? '',
                'description' => $a->notes ?? '',
                'date' => $a->starts_at?->format('Y-m-d') ?? '',
                'time' => $a->starts_at?->format('H:i') ?? '',
                'location' => $a->location ?? '',
                'city' => '',
                'category' => $a->staff ?? '',
                'image_url' => null,
            ])
            ->filter(fn (array $row) => $row['title'] !== '')
            ->values()
            ->all();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'is_active' => $this->is_active,
            'template' => $this->templateKey(),
            'theme' => $this->theme,
        ];
    }

    public static function imageToApi(GalleryImage $g): array
    {
        return [
            'id' => $g->id,
            'url' => $g->url,
            'caption' => $g->caption,
            'disabled' => $g->disabled,
        ];
    }
}
