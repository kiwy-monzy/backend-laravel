@php
    $events = collect($data['events']['items'] ?? [])->sortBy(fn ($e) => $e['date'] ?? '');
    $shown = isset($limit) ? $events->take($limit) : $events;
@endphp

@if ($shown->isNotEmpty())
    <section class="section" id="events">
        <div class="wrap">
            <div class="section-head" data-n="06">
                <h2>{{ __('Events') }}</h2>
            </div>

            <div class="grid c3">
                @foreach ($shown as $event)
                    <a class="card" href="{{ site_url($site, 'event', [$event['id'] ?: \Illuminate\Support\Str::slug($event['title'] ?? '')]) }}">
                        @if (! empty($event['image_url']))
                            <img class="cover" src="{{ $event['image_url'] }}" alt="{{ $event['title'] ?? '' }}" loading="lazy">
                        @endif
                        <div class="body">
                            @if (! empty($event['category']))
                                <span class="pill">{{ $event['category'] }}</span>
                            @endif
                            <h3>{{ $event['title'] ?? '' }}</h3>
                            <p class="meta">
                                {{ trim(($event['date'] ?? '') . ' ' . ($event['time'] ?? '')) }}
                                @if (! empty($event['location'])) · {{ $event['location'] }}@endif
                                @if (! empty($event['city'])), {{ $event['city'] }}@endif
                            </p>
                            <p>{{ $event['description'] ?? '' }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
