@php
    $general = $data['general'] ?? [];
    $social = $general['social_links'] ?? [];
@endphp

<footer class="foot">
    <div class="wrap cols">
        <div>
            <h4>{{ $general['site_name'] ?? $site->name }}</h4>
            <p>{{ \Illuminate\Support\Str::limit(strip_tags($data['about']['description'] ?? ''), 190) }}</p>
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

        <div>
            <h4>{{ __('Follow') }}</h4>
            <ul>
                @foreach (['facebook' => 'Facebook', 'twitter' => 'Twitter', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn'] as $key => $label)
                    @if (! empty($social[$key]))
                        <li><a href="{{ $social[$key] }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a></li>
                    @endif
                @endforeach
            </ul>
        </div>

        <div>
            <h4>{{ __('Get involved') }}</h4>
            <ul>
                <li><a href="{{ site_url($site, 'donate') }}">{{ __('Donate') }}</a></li>
                <li><a href="{{ site_url($site, 'contact') }}">{{ __('Volunteer') }}</a></li>
                <li><a href="{{ site_url($site, 'contact') }}">{{ __('Contact us') }}</a></li>
            </ul>
        </div>
    </div>

    <div class="wrap fine">
        © {{ now()->year }} {{ $general['site_name'] ?? $site->name }}. {{ __('All rights reserved.') }}
    </div>
</footer>
