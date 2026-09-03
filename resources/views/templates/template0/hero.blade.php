@php
    $hero = $data['hero'] ?? [];
    $general = $data['general'] ?? [];
    $stats = $hero['stats'] ?? [];

    // Hero.tsx read this from a hard-coded `.hero-background-image` rule in
    // globals.css. Here it follows the site's own content, falling back to the
    // first gallery image so a new site gets the texture too.
    $photo = $hero['background_image']
        ?? collect($data['gallery']['images'] ?? [])->firstWhere('disabled', false)['url']
        ?? null;

    $primaryLink = $hero['primary_button_link'] ?: site_url($site, 'contact');
    $secondaryLink = $hero['secondary_button_link'] ?: site_url($site, 'about');
@endphp

@if (($general['visibility']['hero'] ?? true) && filled($hero))
    <div class="t0-hero">
        {{-- Order matters: the grid sits at z-1 *over* the tinted layer but
             under the content at z-10, exactly as the JSX stacked them. --}}
        <div class="t0-grid" aria-hidden="true"></div>

        <div class="t0-hero-bg" aria-hidden="true">
            @if ($photo)
                <div class="t0-hero-photo" style="background-image:url('{{ $photo }}')"></div>
            @endif

            <div class="t0-blob one"></div>
            <div class="t0-blob two"></div>
            <div class="t0-blob three"></div>
        </div>

        <div class="t0-floats" aria-hidden="true">
            {{-- lucide `heart`, `users`, `sparkles` — the three that floated in
                 the original, inlined so the page carries no icon library. --}}
            <svg class="f1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            <svg class="f2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <svg class="f3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3z"/>
            </svg>
        </div>

        <div class="t0-hero-inner">
            <div class="t0-hero-copy">
                <div class="t0-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3z"/>
                    </svg>
                    <span>{{ $hero['badge'] ?? __('Empowering Communities Since 2025') }}</span>
                </div>

                <h1>
                    <span class="t0-gradient-text">
                        {{ $hero['title'] ?? $general['site_title'] ?? $site->name }}
                    </span>
                </h1>

                <p class="lead">{{ $hero['description'] ?? '' }}</p>

                <div class="t0-cta">
                    @if (! empty($hero['primary_button_text']))
                        <a class="t0-btn primary" href="{{ $primaryLink }}">
                            <span>{{ $hero['primary_button_text'] }}</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </a>
                    @endif

                    @if (! empty($hero['secondary_button_text']))
                        <a class="t0-btn secondary" href="{{ $secondaryLink }}">
                            <span>{{ $hero['secondary_button_text'] }}</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                            </svg>
                        </a>
                    @endif
                </div>

                @if (filled($stats))
                    <div class="t0-stats">
                        @foreach ($stats as $stat)
                            <div class="item">
                                <div class="v">{{ $stat['value'] ?? '' }}</div>
                                <div class="l">{{ $stat['label'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
