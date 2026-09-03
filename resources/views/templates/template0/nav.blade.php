@php
    $general = $data['general'] ?? [];
    $visibility = $general['visibility'] ?? [];

    // The same eight tabs Navbar.tsx hard-coded, minus any the site has no
    // content for — the original linked to empty pages, which is the one
    // behaviour worth not reproducing.
    $links = array_values(array_filter([
        ['label' => __('Home'), 'href' => site_url($site, 'home'), 'show' => true],
        ['label' => __('Projects'), 'href' => site_url($site, 'projects'), 'show' => filled($data['projects']['items'] ?? [])],
        ['label' => __('Team'), 'href' => site_url($site, 'team'), 'show' => filled($data['team']['members'] ?? [])],
        ['label' => __('Gallery'), 'href' => site_url($site, 'gallery'), 'show' => filled($data['gallery']['images'] ?? [])],
        ['label' => __('Blog'), 'href' => site_url($site, 'blog'), 'show' => filled($data['blog']['posts'] ?? [])],
        ['label' => __('Events'), 'href' => site_url($site, 'events'), 'show' => filled($data['events']['items'] ?? [])],
        ['label' => __('Volunteer'), 'href' => site_url($site, 'contact'), 'show' => true],
        ['label' => __('Contact'), 'href' => site_url($site, 'contact'), 'show' => true],
    ], fn ($l) => $l['show']));
@endphp

<nav class="t0-nav">
    <div class="t0-nav-inner">
        <a class="t0-brand" href="{{ site_url($site, 'home') }}">
            @if (! empty($general['logo_url']))
                <img src="{{ $general['logo_url'] }}" alt="{{ $general['site_name'] ?? $site->name }}">
            @else
                <span class="mark">{{ $general['logo_text'] ?? $general['site_name'] ?? $site->name }}</span>
            @endif
            <span class="full">{{ $general['site_title'] ?? $general['site_name'] ?? $site->name }}</span>
        </a>

        <button class="t0-burger" type="button" aria-label="{{ __('Menu') }}" aria-controls="t0-links">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="t0-links" id="t0-links">
            @if (count($languages ?? []) > 1)
                <span class="lang-switch">
                    @foreach ($languages as $lng)
                        <a href="{{ request()->fullUrlWithQuery(['lang' => $lng]) }}"
                           @class(['on' => ($activeLocale ?? '') === $lng])>{{ strtoupper($lng) }}</a>
                    @endforeach
                </span>
            @endif

            @foreach ($links as $l)
                <a href="{{ $l['href'] }}">{{ $l['label'] }}</a>
            @endforeach

            @if ($visibility['donate'] ?? true)
                <a class="t0-donate" href="{{ site_url($site, 'donate') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                    {{ __('Donate') }}
                </a>
            @endif
        </div>
    </div>
</nav>
