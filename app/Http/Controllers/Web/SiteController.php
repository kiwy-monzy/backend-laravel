<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Website;
use App\Support\Templates;
use App\Support\ThemeFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public website, rendered server-side.
 *
 * **One controller, five templates, the same data.** Every template receives
 * the identical `$data` array — the eleven content sections plus the live
 * gallery — and decides only how to lay it out. That is what makes switching a
 * site's template a cosmetic change rather than a migration.
 */
class SiteController extends Controller
{
    /** `/` resolves by hostname so one deployment can serve every site. */
    public function root(Request $request)
    {
        return $this->render($this->hostSite($request), 'home');
    }

    /*
    |---------------------------------------------------------------------------
    | The host's own site, without a slug in the path
    |---------------------------------------------------------------------------
    |
    | `/about` rather than `/s/fge/about`. These delegate to the same methods
    | the slug routes use, having worked out the site from the hostname instead
    | of from the URL — so there is one implementation of each page and only the
    | question "which site is this?" is answered twice.
    |
    */

    public function hostPage(Request $request, string $page)
    {
        return $this->render($this->hostSite($request), $page);
    }

    public function hostPost(Request $request, string $post)
    {
        return $this->post($this->hostSite($request)->slug, $post);
    }

    public function hostEvent(Request $request, string $event)
    {
        return $this->event($this->hostSite($request)->slug, $event);
    }

    public function hostContact(Request $request): RedirectResponse
    {
        return $this->contact($request, $this->hostSite($request)->slug);
    }

    /**
     * The site this hostname serves.
     *
     * Resolution lives in App\Support\SiteUrl because the link generator needs
     * the same answer — a page served from the root has to emit root-relative
     * links, and the two deciding it separately is how they would drift.
     */
    private function hostSite(Request $request): Website
    {
        $site = \App\Support\SiteUrl::hostSite($request);

        if (! $site) {
            throw new NotFoundHttpException('No website has been set up yet.');
        }

        return $site;
    }

    public function home(string $site)
    {
        return $this->render($this->find($site), 'home');
    }

    public function page(string $site, string $page)
    {
        return $this->render($this->find($site), $page);
    }

    /**
     * A single blog post.
     *
     * Posts live inside the `blog` content section rather than a table, so
     * they are matched in PHP. `slug` is what the links use and `id` is the
     * fallback, because older rows were written before slugs existed.
     */
    public function post(string $site, string $post)
    {
        $website = $this->find($site);
        $data = $website->siteData();

        $found = collect($data['blog']['posts'] ?? [])
            ->filter(fn ($p) => $p['published'] ?? true)
            ->first(fn ($p) => ($p['slug'] ?? '') === $post || ($p['id'] ?? '') === $post);

        if (! $found) {
            throw new NotFoundHttpException('No such post.');
        }

        return $this->render($website, 'post', ['post' => $found]);
    }

    public function event(string $site, string $event)
    {
        $website = $this->find($site);
        $data = $website->siteData();

        $found = collect($data['events']['items'] ?? [])
            ->first(fn ($e) => ($e['id'] ?? '') === $event || Str::slug($e['title'] ?? '') === $event);

        if (! $found) {
            throw new NotFoundHttpException('No such event.');
        }

        return $this->render($website, 'event', ['event' => $found]);
    }

    public function contact(Request $request, string $site): RedirectResponse
    {
        $website = $this->find($site);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Written to both: the lead is where anyone follows it up, and the
        // message row keeps the Website module's own list working for sites
        // that have no CRM module granted.
        Message::create($data + [
            'id' => (string) Str::uuid(),
            'website_id' => $website->id,
            'status' => 'pending',
            'is_read' => false,
            'created_at' => now(),
        ]);

        if (class_exists(\Modules\Crm\Models\Lead::class)) {
            \Modules\Crm\Models\Lead::create($data + [
                'id' => (string) Str::uuid(),
                'organization_id' => $website->organization_id,
                'website_id' => $website->id,
                'source' => 'website_form',
                'status' => 'new',
            ]);
        }

        return back()->with('sent', __('Thank you — your message has been received.'));
    }

    /**
     * Renders the signed-in admin's own site in a template it has not adopted.
     *
     * **Nothing is saved.** The attributes are set on the loaded model and the
     * response is rendered from it, so choosing between five templates does not
     * mean putting each one live on a charity's public site in turn to see what
     * it looks like.
     */
    public function preview(Request $request, string $template)
    {
        $user = $request->user();
        abort_unless($user?->hasAdminAccess(), 403);

        $site = $user->isOwner()
            ? (Website::find(session('chrome.website_id')) ?? $user->website)
            : $user->website;

        abort_unless($site !== null, 404, 'No website to preview.');

        $site->template = Templates::resolve($template);

        // Previewing a splash is the only way to judge one — it is by
        // definition the thing you cannot see once the page has loaded.
        if ($request->filled('splash')) {
            $site->splash = \App\Support\Splash::resolve($request->query('splash'));
        }

        $theme = $request->query('theme');
        if (isset(ThemeFactory::PRESETS[$theme])) {
            // Applied as overrides rather than by setting `theme`: the site's
            // stored `theme` content section outranks the preset, so on a site
            // that has one — FGE does — every preset would otherwise preview
            // identically.
            [$primary, $secondary, $tertiary] = ThemeFactory::PRESETS[$theme]['colors'];
            $site->theme = $theme;
            $site->theme_overrides = [
                'primary' => $primary,
                'secondary' => $secondary,
                'tertiary' => $tertiary,
            ];
        }

        // `sticky` is relative to the *scrolling* box, and inside a preview
        // iframe that is the frame — so the site's sticky navbar rode over the
        // page as the operator scrolled the preview, hiding the very section
        // they were editing. `embedded` unsticks it for the preview only; the
        // real site keeps the sticky nav it was designed with.
        return $this->render($site, $request->query('page', 'home'), ['embedded' => true]);
    }

    private function find(string $slug): Website
    {
        return Website::where('slug', $slug)->firstOr(function () use ($slug) {
            throw new NotFoundHttpException("No website with slug $slug");
        });
    }

    /**
     * Hands the chosen template the site, its data and the page to show.
     *
     * A template that has no partial for a page still renders — `page()` falls
     * back to the home layout — because a missing page should be a thin page,
     * not a 500 on someone's public site.
     */
    private function render(Website $site, string $page, array $extra = [])
    {
        $template = $site->templateKey();
        $locale = $this->resolveLocale($site);

        return view("templates.$template.layout", array_merge([
            'site' => $site,
            'page' => $page,
            'data' => $site->siteData($locale),
            'themeCss' => $site->themeCss(),
            'organization' => $site->organization,
            // The language picker in the nav, and which one is active.
            'languages' => $site->offeredLanguages(),
            'activeLocale' => $locale,
        ], $extra));
    }

    /**
     * Which language to render, from `?lang=`, capped to what the site offers.
     *
     * A request for a language the site does not offer falls back to its
     * default rather than showing empty sections — the query string is a
     * suggestion, not a command.
     */
    private function resolveLocale(Website $site): string
    {
        $requested = request()->query('lang');
        $offered = $site->offeredLanguages();

        return in_array($requested, $offered, true) ? $requested : ($site->default_language ?: 'en');
    }
}
