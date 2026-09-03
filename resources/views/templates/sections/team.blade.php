@php
    $members = collect($data['team']['members'] ?? []);
    // Grouped because the legacy data uses `category` to separate the board
    // from staff from volunteers, and flattening them read as one long list.
    $groups = $members->groupBy(fn ($m) => $m['category'] ?? __('Team'));
@endphp

@if ($members->isNotEmpty() && ($data['general']['visibility']['team'] ?? true))
    <section class="section" id="team">
        <div class="wrap">
            <div class="section-head" data-n="03">
                <h2>{{ __('Our team') }}</h2>
            </div>

            @foreach ($groups as $category => $people)
                @if ($groups->count() > 1)
                    <h3>{{ $category }}</h3>
                @endif
                <div class="grid c4" style="margin-bottom:28px">
                    @foreach ($people as $person)
                        <div class="person">
                            <img src="{{ $person['image'] ?: asset('img/avatar-placeholder.svg') }}"
                                 alt="{{ $person['name'] ?? '' }}" loading="lazy">
                            <strong>{{ $person['name'] ?? '' }}</strong>
                            <div class="role">{{ $person['role'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
@endif
