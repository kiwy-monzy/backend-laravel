<?php

use App\Models\Website;
use App\Support\SiteUrl;

if (! function_exists('site_url')) {
    /**
     * The URL of a public page — unprefixed on the host's own site, `/s/{slug}`
     * elsewhere. See App\Support\SiteUrl.
     *
     * A global function rather than a facade because the templates call it on
     * nearly every line, and `{{ site_url($site, 'about') }}` is the shape a
     * Blade file wants to read.
     *
     * @param  array<int|string,mixed>  $params
     */
    function site_url(Website $site, string $page = 'home', array $params = []): string
    {
        return SiteUrl::to($site, $page, $params);
    }
}
