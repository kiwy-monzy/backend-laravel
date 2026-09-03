@php
    $splash = \App\Support\Splash::resolve($site->splash);
    if ($splash === 'none') return;

    $general = $data['general'] ?? [];
    $name = $general['logo_text'] ?? $general['site_name'] ?? $site->name;
    $logo = $general['logo_url'] ?? null;
    $tagline = $site->splash_tagline
        ?: ($organization?->name ?? $general['site_title'] ?? '');
    $seconds = max(1, min(10, (int) ($site->splash_seconds ?: 2)));
@endphp

{{--
    Shown before the page, removed when the page is ready.

    **Pure CSS, and it hides itself.** The animation carries `forwards` and
    ends in `visibility: hidden`, so even with JavaScript disabled or broken
    the splash cannot strand a visitor on a blank brand panel — the worst
    failure this feature has. The script below only makes it leave *earlier*
    than the timer when the load finishes first.
--}}
<div class="splash splash-{{ $splash }}" id="site-splash" aria-hidden="true"
     style="--splash-hold: {{ $seconds }}s">
    <div class="splash-inner">
        @if ($logo)
            <img class="splash-logo" src="{{ $logo }}" alt="">
        @else
            <div class="splash-mark">{{ mb_substr($name, 0, 3) }}</div>
        @endif

        <div class="splash-name">{{ $name }}</div>

        @if ($tagline)
            <div class="splash-tagline">{{ $tagline }}</div>
        @endif

        @if ($splash === 'bar')
            <div class="splash-bar"><span></span></div>
        @endif
    </div>
</div>

<script>
    // Dismiss as soon as the page is usable; the CSS timer is the ceiling,
    // not the duration. `pageshow` rather than `load` so a back-button
    // restore from the bfcache does not show the splash again.
    (function () {
        var el = document.getElementById("site-splash");
        if (!el) return;
        function go() { el.classList.add("splash-done"); }
        if (document.readyState === "complete") go();
        else window.addEventListener("load", go, { once: true });
        window.addEventListener("pageshow", function (e) { if (e.persisted) go(); });
    })();
</script>
