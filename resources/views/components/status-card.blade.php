@props([
    'name',
    'count',
    'icon' => '▦',
    'color' => '#1a1a1a',
    'href' => null,
])

{{--
    The status card from `packages/dashboard/src/components/StatusCard.jsx`.

    **This is a copy, not an interpretation.** The illustration in
    `_statuscard-art.blade.php` is the 86-path artwork out of the original,
    converted from JSX attribute names to SVG ones and otherwise untouched;
    the viewBox, the 19.5px corner radius, the 90px count, the 28px label, the
    translucent circle behind the icon and the animated gradient mesh are all
    the original's. The React version picked its icon by sniffing the label for
    words like "chat" — none of which occur in a road register — so that is a
    prop here, and `count`/`name` come from the caller instead of props with
    the same names. Nothing else was changed.
--}}

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="statcard-container" style="--sc-color: {{ $color }}">
    <div class="gradient-overlay"><div class="gradient-mesh"></div></div>

    <svg viewBox="0 0 320.752 185.154" width="100%" preserveAspectRatio="xMidYMid meet"
         style="position:relative;z-index:1;display:block">
        <defs>
            <clipPath id="sc-clip-{{ $sc = uniqid() }}">
                <rect class="a" width="301.752" height="183.153" rx="19.5" />
            </clipPath>
        </defs>

        <g transform="translate(-597 -136)">
            <g clip-path="url(#sc-clip-{{ $sc }})" transform="translate(597.5 136)">
                @include('components._statuscard-art')
            </g>

            {{-- Label, count and the icon disc — the original's positions. --}}
            <g transform="translate(287 22)">
                <g transform="translate(-0.347 -1)">
                    <text class="sc-h" transform="translate(464.347 123)">
                        <tspan x="150" y="30" text-anchor="end">{{ $name }}</tspan>
                    </text>
                    <circle class="sc-circle" cx="29.5" cy="29.5" r="25.5" transform="translate(555 236)" />
                    <text class="sc-icon-glyph" transform="translate(555 236)">
                        <tspan x="29.5" y="38" text-anchor="middle">{{ $icon }}</tspan>
                    </text>
                </g>
                <text class="sc-j" transform="translate(416 129.5)">
                    <tspan x="200" y="100" text-anchor="end">{{ $count }}</tspan>
                </text>
            </g>
        </g>
    </svg>
</{{ $tag }}>
