@php
    $posts = collect($data['blog']['posts'] ?? [])
        ->filter(fn ($p) => $p['published'] ?? true)
        ->sortByDesc(fn ($p) => $p['publish_date'] ?? '');
    $shown = isset($limit) ? $posts->take($limit) : $posts;
@endphp

@if ($shown->isNotEmpty())
    <section class="section" id="blog">
        <div class="wrap">
            <div class="section-head" data-n="05">
                <h2>{{ __('Latest news') }}</h2>
            </div>

            <div class="grid c3">
                @foreach ($shown as $post)
                    <a class="card" href="{{ site_url($site, 'post', [$post['slug'] ?: $post['id']]) }}">
                        {{-- Only `featured_image`, matching the original. The
                             `image` field also exists on these rows but holds
                             the full-size original, and rendering it here is
                             what made one post fill the section. --}}
                        @if (! empty($post['featured_image']))
                            <img class="cover" src="{{ $post['featured_image'] }}" alt="{{ $post['title'] ?? '' }}" loading="lazy">
                        @endif
                        <div class="body">
                            <h3>{{ $post['title'] ?? '' }}</h3>
                            <p class="meta">
                                {{ $post['author'] ?? '' }}
                                @if (! empty($post['publish_date'])) · {{ $post['publish_date'] }} @endif
                            </p>
                            <p>{{ $post['excerpt'] ?: \Illuminate\Support\Str::limit(strip_tags($post['description'] ?? ''), 160) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif
