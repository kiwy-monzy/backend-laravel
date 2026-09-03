@php
    $general = $data['general'] ?? [];
    $social = $general['social_links'] ?? [];

    $socialIcons = [
        'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'twitter' => '<path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>',
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
        'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
    ];
@endphp

<footer class="foot" id="contact">
    <div class="wrap cols">
        <div>
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     width="32" height="32" style="color:#4ade80">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <h3 style="margin:0;font-size:1.5rem;font-weight:700;color:#fff">
                    {{ $general['logo_text'] ?? $general['site_name'] ?? $site->name }}
                </h3>
            </div>

            <p>{{ \Illuminate\Support\Str::limit(strip_tags($data['about']['description'] ?? ''), 190) }}</p>

            <div style="display:flex;gap:1rem;margin-top:1.5rem">
                @foreach ($socialIcons as $key => $path)
                    @if (! empty($social[$key]))
                        <a href="{{ $social[$key] }}" target="_blank" rel="noopener noreferrer"
                           aria-label="{{ ucfirst($key) }}"
                           style="width:2.5rem;height:2.5rem;background:rgba(255,255,255,.1);border-radius:9999px;display:flex;align-items:center;justify-content:center">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 width="20" height="20">{!! $path !!}</svg>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        <div>
            <h4>{{ __('Quick Links') }}</h4>
            <ul>
                <li><a href="{{ site_url($site, 'home') }}">{{ __('Home') }}</a></li>
                <li><a href="{{ site_url($site, 'about') }}">{{ __('About') }}</a></li>
                <li><a href="{{ site_url($site, 'projects') }}">{{ __('Projects') }}</a></li>
                <li><a href="{{ site_url($site, 'gallery') }}">{{ __('Gallery') }}</a></li>
            </ul>
        </div>

        <div>
            <h4>{{ __('Get Involved') }}</h4>
            <ul>
                <li><a href="{{ site_url($site, 'donate') }}">{{ __('Donate') }}</a></li>
                <li><a href="{{ site_url($site, 'contact') }}">{{ __('Volunteer') }}</a></li>
                <li><a href="{{ site_url($site, 'contact') }}">{{ __('Contact us') }}</a></li>
            </ul>
        </div>

        <div>
            <h4>{{ __('Contact') }}</h4>
            <ul>
                @if (! empty($general['contact_email']))
                    <li><a href="mailto:{{ $general['contact_email'] }}">{{ $general['contact_email'] }}</a></li>
                @endif
                @if (! empty($general['contact_phone']))
                    <li><a href="tel:{{ preg_replace('/\s+/', '', $general['contact_phone']) }}">{{ $general['contact_phone'] }}</a></li>
                @endif
                @if (! empty($general['address']))
                    <li>{{ $general['address'] }}</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="wrap fine">
        © {{ now()->year }} {{ $general['site_name'] ?? $site->name }}. {{ __('All rights reserved.') }}
    </div>
</footer>
