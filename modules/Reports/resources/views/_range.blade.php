<p class="nav range-nav">
    @foreach (\App\Support\DateRange::PRESETS as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['range' => $key]) }}"
           @class(['on' => $range->key === $key])>{{ __($label) }}</a>
    @endforeach
    <span class="spacer"></span>
    <span class="dim small">{{ $range->caption() }}</span>
</p>
