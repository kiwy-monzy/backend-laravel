@php
    $items = $data['projects']['items'] ?? [];
@endphp

@if (filled($items) && ($data['general']['visibility']['projects'] ?? true))
    <section class="section" id="projects">
        <div class="wrap">
            <div class="section-head" data-n="02">
                <h2>{{ __('Projects') }}</h2>
                <p>{{ __('What we are working on, and where it has reached.') }}</p>
            </div>

            <div class="grid c3">
                @foreach ($items as $item)
                    <article class="card">
                        @if (! empty($item['image']))
                            <img class="cover" src="{{ $item['image'] }}" alt="{{ $item['title'] ?? '' }}" loading="lazy">
                        @endif
                        <div class="body">
                            @if (! empty($item['status']))
                                <span class="pill">{{ $item['status'] }}</span>
                            @endif
                            <h3>{{ $item['title'] ?? '' }}</h3>
                            @if (! empty($item['subtitle']))
                                <p class="meta">{{ $item['subtitle'] }}</p>
                            @endif
                            <p>{{ $item['description'] ?? '' }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
