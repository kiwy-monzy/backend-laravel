<article class="section article">
    <div class="wrap">
        <p class="meta">
            <a href="{{ site_url($site, 'events') }}">← {{ __('All events') }}</a>
        </p>

        @if (! empty($event['category']))
            <span class="pill">{{ $event['category'] }}</span>
        @endif

        <h1>{{ $event['title'] ?? '' }}</h1>

        <p class="meta">
            {{ trim(($event['date'] ?? '') . ' ' . ($event['time'] ?? '')) }}
            @if (! empty($event['location'])) · {{ $event['location'] }}@endif
            @if (! empty($event['city'])), {{ $event['city'] }}@endif
        </p>

        @if (! empty($event['image_url']))
            <img class="article-cover" src="{{ $event['image_url'] }}" alt="{{ $event['title'] ?? '' }}">
        @endif

        <div class="prose">
            <p>{{ $event['description'] ?? '' }}</p>
        </div>

        @if (filled($event['tags'] ?? []))
            <p class="meta">
                @foreach ($event['tags'] as $tag)<span class="pill">{{ $tag }}</span> @endforeach
            </p>
        @endif

        <p style="margin-top:28px">
            <a class="btn primary" href="{{ site_url($site, 'contact') }}">
                {{ __('Ask about this event') }}
            </a>
        </p>
    </div>
</article>
