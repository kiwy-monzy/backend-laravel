@php
    // Disabled images are hidden here rather than filtered in the query, so the
    // admin's gallery page and the public page stay one list with one flag.
    $images = collect($data['gallery']['images'] ?? [])->reject(fn ($i) => $i['disabled'] ?? false);
    $limit = $limit ?? null;
    $shown = $limit ? $images->take($limit) : $images;
@endphp

@if ($shown->isNotEmpty() && ($data['general']['visibility']['gallery'] ?? true))
    <section class="section" id="gallery">
        <div class="wrap">
            <div class="section-head" data-n="04">
                <h2>{{ __('Gallery') }}</h2>
            </div>

            <div class="gallery">
                @foreach ($shown as $image)
                    <figure>
                        <img src="{{ $image['url'] }}" alt="{{ $image['caption'] ?? '' }}" loading="lazy">
                        @if (! empty($image['caption']))
                            <figcaption>{{ $image['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>

            @if ($limit && $images->count() > $limit)
                <p style="margin-top:20px">
                    <a class="btn ghost" href="{{ site_url($site, 'gallery') }}">
                        {{ __('See all :count photos', ['count' => $images->count()]) }}
                    </a>
                </p>
            @endif
        </div>
    </section>
@endif
