@php
    $hero = $data['hero'] ?? [];
    $general = $data['general'] ?? [];
@endphp

@if (($general['visibility']['hero'] ?? true) && filled($hero))
    <section class="hero">
        <div class="wrap">
            <div>
                <span class="eyebrow">{{ $general['site_name'] ?? $site->name }}</span>
                <h1>{{ $hero['title'] ?? $general['site_title'] ?? $site->name }}</h1>
                <p class="lead">{{ $hero['description'] ?? '' }}</p>

                <div class="cta">
                    @if (! empty($hero['primary_button_text']))
                        <a class="btn primary" href="{{ $hero['primary_button_link'] ?: site_url($site, 'donate') }}">
                            {{ $hero['primary_button_text'] }}
                        </a>
                    @endif
                    @if (! empty($hero['secondary_button_text']))
                        <a class="btn ghost" href="{{ $hero['secondary_button_link'] ?: site_url($site, 'about') }}">
                            {{ $hero['secondary_button_text'] }}
                        </a>
                    @endif
                </div>
            </div>

            {{-- Template 4 pins the donate call to action beside the hero; every
                 other template renders the hero on its own. --}}
            @if (($variant ?? '') === 'template4')
                @include('templates.sections._donate-rail')
            @endif
        </div>
    </section>
@endif
