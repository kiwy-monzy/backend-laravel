<!DOCTYPE html>
<html lang="{{ $activeLocale ?? 'en' }}">
<head>
    @include('templates._head')
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/template0.css') }}">
</head>
{{--
    Template 0 — the FGE React frontend, ported.

    It has its own nav, hero and footer rather than the shared ones, because
    those three are where the identity lives: the floating pill navbar, the
    masked background grid, the tri-colour gradient headline. Everything below
    the hero is the shared section partials, restyled by template0.css — so a
    content change still lands on all six templates at once.
--}}
@php
    // Only the home page leads with the hero, whose huge top padding is what
    // absorbs the navbar's -6rem lift. Every other page starts on a plain
    // section, so it needs its own top clearance or the floating navbar sits
    // on top of the first heading — see `body.t0-inner` in template0.css.
    $heroLed = ($page ?? 'home') === 'home';
@endphp
<body class="t0 @unless ($heroLed) t0-inner @endunless @if ($embedded ?? false) embedded @endif">
    @include('templates._splash')

    @include('templates.template0.nav')

    <main>
        @include('templates.template0.page')
    </main>

    @include('templates.template0.footer')

    <script>
        // The one piece of interactivity the JSX had that markup alone cannot
        // do: the mobile menu. Everything else in the original was CSS
        // transitions, which survive the port as CSS.
        document.querySelector('.t0-burger')?.addEventListener('click', function () {
            document.getElementById('t0-links')?.classList.toggle('open');
        });
    </script>
</body>
</html>
