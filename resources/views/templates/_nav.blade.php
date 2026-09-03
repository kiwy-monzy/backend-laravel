@php
    $general = $data['general'] ?? [];
    $visibility = $general['visibility'] ?? [];

    // The nav is derived from what the site actually has. A charity with no
    // blog posts should not have a Blog tab that leads to an empty page.
    $links = array_values(array_filter([
        ['label' => __('Home'), 'href' => site_url($site, 'home'), 'key' => 'home', 'show' => true],
        ['label' => __('About'), 'href' => site_url($site, 'about'), 'key' => 'about', 'show' => filled($data['about'] ?? null) && ($visibility['about'] ?? true)],
        ['label' => __('Projects'), 'href' => site_url($site, 'projects'), 'key' => 'projects', 'show' => filled($data['projects']['items'] ?? []) && ($visibility['projects'] ?? true)],
        ['label' => __('Gallery'), 'href' => site_url($site, 'gallery'), 'key' => 'gallery', 'show' => filled($data['gallery']['images'] ?? []) && ($visibility['gallery'] ?? true)],
        ['label' => __('Events'), 'href' => site_url($site, 'events'), 'key' => 'events', 'show' => filled($data['events']['items'] ?? [])],
        ['label' => __('Blog'), 'href' => site_url($site, 'blog'), 'key' => 'blog', 'show' => filled($data['blog']['posts'] ?? [])],
        ['label' => __('Team'), 'href' => site_url($site, 'team'), 'key' => 'team', 'show' => filled($data['team']['members'] ?? []) && ($visibility['team'] ?? true)],
        ['label' => __('Contact'), 'href' => site_url($site, 'contact'), 'key' => 'contact', 'show' => true],
    ], fn ($l) => $l['show']));
@endphp

<header class="nav">
    <div class="wrap nav-inner">
        <a class="nav-brand" href="{{ site_url($site, 'home') }}">
            @if (! empty($general['logo_url']))
                <img src="{{ $general['logo_url'] }}" alt="">
            @endif
            <span>{{ $general['logo_text'] ?? $general['site_name'] ?? $site->name }}</span>
        </a>

        <button class="nav-toggle" type="button" aria-label="{{ __('Menu') }}"
                onclick="document.getElementById('nav-links').classList.toggle('open')">☰</button>

        <nav class="nav-links" id="nav-links">
                @if (count($languages ?? []) > 1)
                    <span class="lang-switch">
                        @foreach ($languages as $lng)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $lng]) }}"
                               @class(['on' => ($activeLocale ?? '') === $lng])>{{ strtoupper($lng) }}</a>
                        @endforeach
                    </span>
                @endif

            @foreach ($links as $l)
                <a href="{{ $l['href'] }}" @class(['on' => $page === $l['key']])>{{ $l['label'] }}</a>
            @endforeach

            @if (($visibility['donate'] ?? true))
                <a class="btn primary" href="{{ site_url($site, 'donate') }}">{{ __('Donate') }}</a>
            @endif
        </nav>
    </div>
</header>
