@php
    $about = $data['about'] ?? [];
@endphp

@if (filled($about) && ($data['general']['visibility']['about'] ?? true))
    <section class="section" id="about">
        <div class="wrap">
            <div class="section-head" data-n="01">
                <h2>{{ $about['title'] ?? __('About us') }}</h2>
                <p>{{ $about['description'] ?? '' }}</p>
            </div>

            <div class="grid c2">
                @if (! empty($about['mission']))
                    <div class="card"><div class="body">
                        <h3>{{ __('Our mission') }}</h3>
                        <p>{{ $about['mission'] }}</p>
                    </div></div>
                @endif

                @if (! empty($about['vision']))
                    <div class="card"><div class="body">
                        <h3>{{ __('Our vision') }}</h3>
                        <p>{{ $about['vision'] }}</p>
                    </div></div>
                @endif
            </div>
        </div>
    </section>
@endif
