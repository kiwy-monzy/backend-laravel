@php
    use Illuminate\Support\Str;

    $general = $data['general'] ?? [];

    // Every tag falls back through site settings → content → the site's own
    // name, so a site that has filled in nothing still emits a sensible head
    // rather than empty attributes search engines will ignore.
    $title = $site->meta_title
        ?: ($general['site_title'] ?? $general['site_name'] ?? $site->name);

    $description = $site->meta_description
        ?: Str::limit(strip_tags($data['about']['description'] ?? ''), 155);

    $image = $site->og_image
        ?: ($general['logo_url'] ?? null)
        ?: collect($data['gallery']['images'] ?? [])->firstWhere('disabled', false)['url'] ?? null;

    // Absolute URLs: a relative og:image is silently dropped by every scraper.
    $absolute = fn (?string $path) => $path
        ? (Str::startsWith($path, ['http://', 'https://']) ? $path : url($path))
        : null;

    $canonical = $site->canonical_url ?: url()->current();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $title }}</title>
@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
@if ($site->meta_keywords)
    <meta name="keywords" content="{{ $site->meta_keywords }}">
@endif
<meta name="robots" content="{{ $site->robots ?: 'index,follow' }}">
<link rel="canonical" href="{{ $canonical }}">

{{-- Open Graph: what Facebook, LinkedIn and WhatsApp read when the link is
     pasted. Without og:image they pick an arbitrary image off the page. --}}
<meta property="og:site_name" content="{{ $general['site_name'] ?? $site->name }}">
<meta property="og:type" content="{{ $site->og_type ?: 'website' }}">
<meta property="og:title" content="{{ $title }}">
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
<meta property="og:url" content="{{ $canonical }}">
@if ($image)
    <meta property="og:image" content="{{ $absolute($image) }}">
    <meta property="og:image:alt" content="{{ $title }}">
@endif

<meta name="twitter:card" content="{{ $site->twitter_card ?: 'summary_large_image' }}">
@if ($site->twitter_site)
    <meta name="twitter:site" content="{{ $site->twitter_site }}">
@endif
<meta name="twitter:title" content="{{ $title }}">
@if ($description)
    <meta name="twitter:description" content="{{ $description }}">
@endif
@if ($image)
    <meta name="twitter:image" content="{{ $absolute($image) }}">
@endif

@if (! empty($general['logo_url']))
    <link rel="icon" href="{{ $general['logo_url'] }}">
@endif

<link rel="stylesheet" href="{{ \App\Support\Asset::v('css/site.css') }}">

{{-- The palette, inline and above the stylesheet's cascade point, so the page
     never paints in the fallback colours first. --}}
<style>{!! $themeCss !!}</style>
