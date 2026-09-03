<?php

namespace Modules\Website\Http\Controllers;

use App\Http\Controllers\Web\AdminController;
use App\Models\Organization;
use App\Models\Website;
use App\Support\Splash;
use App\Support\Templates;
use App\Support\ThemeFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The estate of websites. Owner-only, except for the site switcher.
 */
class WebsiteController extends AdminController
{
    public function index()
    {
        $this->ownerOnly();

        return view('website::websites.index', [
            'websites' => Website::withCount(['sections', 'galleryImages', 'users'])
                ->orderBy('created_at')->get(),
        ]);
    }

    public function create()
    {
        $this->ownerOnly();

        return view('website::websites.form', [
            'website' => new Website([
                'template' => 'template0', 'theme' => 'fge-custom',
                'is_active' => true, 'splash' => 'none', 'splash_seconds' => 2,
            ]),
            'templates' => Templates::ALL,
            'themes' => ThemeFactory::PRESETS,
            'splashes' => Splash::ALL,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ownerOnly();

        $data = $this->validated($request);

        // The look lives on the organization profile now: a new site's
        // template and palette are written there, and the site is created with
        // its own look columns null so it always renders in the profile's.
        $presentation = array_intersect_key($data, array_flip(['template', 'theme', 'theme_overrides']));

        $website = Website::create(array_diff_key($data, $presentation) + [
            'id' => (string) Str::uuid(),
            'owner_id' => $this->me()->id,
            'organization_id' => $this->me()->organization_id,
        ]);

        if ($presentation !== [] && $website->organization_id) {
            Organization::whereKey($website->organization_id)
                ->update(array_filter($presentation, fn ($v) => $v !== null));
        }

        return redirect()->route('website.sites.edit', $website)
            ->with('status', __('Website created.'));
    }

    public function edit(Website $website)
    {
        $this->ownerOnly();

        return view('website::websites.form', [
            'website' => $website,
            'templates' => Templates::ALL,
            'themes' => ThemeFactory::PRESETS,
            'splashes' => Splash::ALL,
        ]);
    }

    public function update(Request $request, Website $website): RedirectResponse
    {
        $this->ownerOnly();

        $website->update($this->validated($request, $website));

        return back()->with('status', __('Website saved.'));
    }

    public function destroy(Website $website): RedirectResponse
    {
        $this->ownerOnly();

        // FGE is the site every legacy row points at; deleting it would orphan
        // the imported content rather than tidy anything up.
        if ($website->id === Website::FGE_WEBSITE_ID) {
            return back()->with('error', __('The FGE site cannot be deleted.'));
        }

        $website->delete();

        if (session('chrome.website_id') === $website->id) {
            session()->forget('chrome.website_id');
        }

        return redirect()->route('website.sites.index')->with('status', __('Website deleted.'));
    }

    /** The titlebar picker. Owners only — everyone else has one site by definition. */
    public function switch(Request $request): RedirectResponse
    {
        $this->ownerOnly();

        $id = $request->input('website_id');
        session(['chrome.website_id' => Website::whereKey($id)->exists() ? $id : null]);

        return back();
    }

    private function validated(Request $request, ?Website $website = null): array
    {
        $unique = 'unique:websites,slug'.($website ? ','.$website->id.',id' : '');

        // The template, palette and overrides are chosen on the organization
        // profile for an existing site; only the create form still posts them
        // (and they are written to the organization, never the website).
        $creating = $website === null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', $unique],
            'domain' => ['nullable', 'string', 'max:190'],
            'template' => [$creating ? 'required' : 'sometimes', 'in:'.implode(',', array_keys(Templates::ALL))],
            'theme' => [$creating ? 'required' : 'sometimes', 'in:'.implode(',', array_keys(ThemeFactory::PRESETS))],
            'is_active' => ['nullable', 'boolean'],
            'splash' => ['required', 'in:'.implode(',', array_keys(Splash::ALL))],
            'splash_seconds' => ['nullable', 'integer', 'between:1,10'],
            'splash_tagline' => ['nullable', 'string', 'max:120'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'meta_keywords' => ['nullable', 'string', 'max:190'],
            'canonical_url' => ['nullable', 'string', 'max:500'],
            'robots' => ['required', Rule::in(['index,follow', 'noindex,nofollow', 'index,nofollow', 'noindex,follow'])],
            'og_image' => ['nullable', 'string', 'max:500'],
            'og_type' => ['nullable', 'string', 'max:40'],
            'twitter_card' => ['nullable', 'in:summary,summary_large_image'],
            'twitter_site' => ['nullable', 'string', 'max:60'],
            'default_language' => ['required', 'string', 'max:8'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:8'],
            'override_primary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'override_secondary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'override_tertiary' => ['nullable', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
        ]);

        $overrides = array_filter([
            'primary' => $data['override_primary'] ?? null,
            'secondary' => $data['override_secondary'] ?? null,
            'tertiary' => $data['override_tertiary'] ?? null,
        ]);

        $presentation = [
            'template' => $data['template'] ?? null,
            'theme' => $data['theme'] ?? null,
            'theme_overrides' => $overrides ?: null,
        ];

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'splash' => $data['splash'],
            'splash_seconds' => $data['splash_seconds'] ?? 2,
            'splash_tagline' => $data['splash_tagline'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'canonical_url' => $data['canonical_url'] ?? null,
            'robots' => $data['robots'],
            'og_image' => $data['og_image'] ?? null,
            'og_type' => $data['og_type'] ?? 'website',
            'twitter_card' => $data['twitter_card'] ?? 'summary_large_image',
            'twitter_site' => $data['twitter_site'] ?? null,
            'default_language' => $data['default_language'] ?: 'en',
            'languages' => array_values(array_unique(array_filter($data['languages'] ?? []))) ?: null,
        ] + ($creating ? $presentation : []);
    }

    private function ownerOnly(): void
    {
        if (! $this->me()->isOwner()) {
            throw new AccessDeniedHttpException('Only an owner manages websites.');
        }
    }
}
