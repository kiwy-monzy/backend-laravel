@php
    // Posts carry `content` as a list of typed blocks; older rows only have
    // `description`. Rendering both means a post written under either schema
    // still reads properly.
    $blocks = $post['content'] ?? [];
@endphp

<article class="section article">
    <div class="wrap">
        <p class="meta">
            <a href="{{ site_url($site, 'blog') }}">← {{ __('All news') }}</a>
        </p>

        <h1>{{ $post['title'] ?? '' }}</h1>

        <p class="meta">
            {{ $post['author'] ?? '' }}
            @if (! empty($post['publish_date'])) · {{ $post['publish_date'] }} @endif
        </p>

        @if (! empty($post['featured_image']))
            <img class="article-cover" src="{{ $post['featured_image'] }}" alt="{{ $post['title'] ?? '' }}">
        @endif

        @if (! empty($post['excerpt']))
            <p class="lead">{{ $post['excerpt'] }}</p>
        @endif

        <div class="prose">
            @forelse ($blocks as $block)
                @switch($block['type'] ?? 'paragraph')
                    @case('heading')
                        <h2>{{ $block['value'] ?? '' }}</h2>
                        @break
                    @case('image')
                        <img src="{{ $block['value'] ?? '' }}" alt="" loading="lazy">
                        @break
                    @case('quote')
                        <blockquote>{{ $block['value'] ?? '' }}</blockquote>
                        @break
                    @default
                        <p>{{ $block['value'] ?? '' }}</p>
                @endswitch
            @empty
                <p>{{ $post['description'] ?? '' }}</p>
            @endforelse
        </div>
    </div>
</article>
