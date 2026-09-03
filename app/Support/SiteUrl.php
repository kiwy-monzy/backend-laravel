<?php

namespace App\Support;

use App\Models\Website;
use Illuminate\Http\Request;

/**
 * Where a public page lives.
 *
 * One deployment serves every tenant, so a site can be addressed two ways:
 *
 *   by host   fge.or.tz/about        — the site the hostname resolves to
 *   by slug   /s/fge/about           — any site, from anywhere
 *
 * The host form is the real one; the slug form exists so the admin can preview
 * a site it is not currently hosting, and so a developer on localhost can reach
 * a second tenant at all. Templates should not have to know which they are in,
 * so they ask here and get whichever is right for the request in hand: pages of
 * the host's own site come out unprefixed, everything else keeps its slug.
 */
final class SiteUrl
{
    /**
     * Pages reachable without a slug, and the route that serves each.
     *
     * `home` is the root itself; the rest are the literal top-level paths.
     */
    private const HOST_ROUTES = [
        'home' => 'site.root',
        'about' => 'site.host.about',
        'projects' => 'site.host.projects',
        'gallery' => 'site.host.gallery',
        'blog' => 'site.host.blog',
        'post' => 'site.host.post',
        'events' => 'site.host.events',
        'event' => 'site.host.event',
        'team' => 'site.host.team',
        'donate' => 'site.host.donate',
        'contact' => 'site.host.contact',
        'contact.send' => 'site.host.contact.send',
    ];

    /**
     * Resolved once per hostname, so `root()` and every link on the page agree.
     *
     * Keyed by host rather than a single slot because a queue worker or a test
     * run serves several hosts in one process, and a plain memo would hand the
     * second one the first one's site.
     *
     * @var array<string,Website|null>
     */
    private static array $hostSites = [];

    /**
     * The site this request's hostname is for.
     *
     * Falls back to FGE and then to the oldest active site, which is what makes
     * `/` work on localhost, where no domain matches anything.
     */
    public static function hostSite(?Request $request = null): ?Website
    {
        $host = strtolower(($request ?? request())->getHost());

        if (array_key_exists($host, self::$hostSites)) {
            return self::$hostSites[$host];
        }

        return self::$hostSites[$host] = Website::where('is_active', true)
            ->where(fn ($q) => $q->where('domain', $host)->orWhere('domain', 'www.' . $host))
            ->first()
            ?? Website::find(Website::FGE_WEBSITE_ID)
            ?? Website::where('is_active', true)->orderBy('created_at')->first();
    }

    /** Drop the memo — for tests that change which sites exist mid-run. */
    public static function forget(): void
    {
        self::$hostSites = [];
    }

    public static function isHost(Website $site): bool
    {
        return self::hostSite()?->getKey() === $site->getKey();
    }

    /**
     * The URL of one page of one site.
     *
     * @param  array<int|string,mixed>  $params  extra segments — a post slug, an event id
     */
    public static function to(Website $site, string $page = 'home', array $params = []): string
    {
        if (self::isHost($site) && isset(self::HOST_ROUTES[$page])) {
            return route(self::HOST_ROUTES[$page], $params);
        }

        return route('site.' . $page, array_merge([$site->slug], $params));
    }
}
