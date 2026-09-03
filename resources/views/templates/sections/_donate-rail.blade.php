@php
    $donate = $data['donate'] ?? [];
@endphp

<aside class="donate-rail">
    <h3>{{ $donate['title'] ?? __('Support this work') }}</h3>
    <p>{{ \Illuminate\Support\Str::limit($donate['description'] ?? __('Every contribution goes directly into the programmes on this page.'), 180) }}</p>
    <a class="btn primary block" href="{{ site_url($site, 'donate') }}">{{ __('Donate now') }}</a>
</aside>
