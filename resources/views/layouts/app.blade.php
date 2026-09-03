<!DOCTYPE html>
{{-- The theme and text size are attributes on the root element, set
     server-side from the session. Doing it here rather than in a script means
     the page renders in the right theme immediately instead of flashing the
     default first. --}}
<html lang="{{ $appLocale ?? 'en' }}"
      data-theme="{{ $appTheme ?? 'light' }}"
      data-font="{{ $appFontSize ?? 'normal' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FGE Admin')</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/app.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/chrome.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/widgets.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/admin.css') }}">
    <script defer src="{{ \App\Support\Asset::v('js/widgets.js') }}"></script>
    <script defer src="{{ \App\Support\Asset::v('js/chrome.js') }}"></script>
    <script defer src="{{ \App\Support\Asset::v('js/admin.js') }}"></script>
    <script defer src="{{ \App\Support\Asset::v('js/picker.js') }}"></script>
    {{-- The data grid is a bundle (React underneath), loaded only on pages that
         mount one. Its stylesheet is Glide's, plus the chrome around it. --}}
    @if (file_exists(public_path('js/grid.js')))
        <link rel="stylesheet" href="{{ \App\Support\Asset::v('js/grid.css') }}">
        <script defer src="{{ \App\Support\Asset::v('js/grid.js') }}"></script>
    @endif

    {{-- Read the collapsed state before the first paint. In `chrome.js` this
         would run after the rail had already been painted wide, so a collapsed
         rail would visibly snap shut on every navigation. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem("fge_sb_collapsed") === "1") {
                    document.documentElement.dataset.sbCollapsed = "1";
                }
            } catch (e) { /* private mode: start expanded */ }
        })();
    </script>

    {{-- For stylesheets a page needs before first paint — a module that ships
         its own vendor CSS, say. Scripts belong in the `scripts` stack. --}}
    @stack('head')
</head>
@php
    $user = auth()->user();
    $sections = \App\Support\Nav::sections($user);
    $footerNav = \App\Support\Nav::footer($user);
    $moduleSections = \App\Support\ModuleNav::sections($user);
    $moduleLabel = \App\Support\ModuleNav::activeLabel();
@endphp
<body class="has-chrome @yield('body_class', '')">

{{-- ── Title bar ───────────────────────────────────────────────────────────── --}}
<header class="tb">
    <div class="tb-left">
        <span class="tb-logo" aria-hidden="true"></span>
        <a class="tb-name" href="{{ $user ? route('dashboard') : route('login') }}">
            {{ $currentWebsite->name ?? 'FGE' }}
        </a>
    </div>

    <button class="tb-btn" id="sb-toggle" type="button" title="{{ __('Toggle sidebar') }}"
            aria-label="{{ __('Toggle sidebar') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/>
        </svg>
    </button>

    @auth
        <div class="tb-search" id="tb-search">
            <div class="tb-search-inner">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" placeholder="{{ __('Search everything…') }}"
                       data-empty="{{ __('No results') }}" autocomplete="off" spellcheck="false">
                <button class="tb-clear" type="button" aria-label="{{ __('Clear search') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                        <line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="tb-results"></div>
        </div>
    @endauth

    <span class="tb-spacer"></span>

    {{-- The organization being worked in. Someone seated on more than one — the
         system admin across Knowlia and the agencies it runs — switches here;
         everyone else sees the single tenant they belong to. --}}
    @auth
        @if (($myOrganizations ?? collect())->count() > 1)
            <form class="tb-site" method="POST" action="{{ route('organization.switch') }}">@csrf
                <select name="organization_id" onchange="this.form.submit()" aria-label="{{ __('Active organization') }}">
                    @foreach ($myOrganizations as $o)
                        <option value="{{ $o->id }}" @selected($user->organization_id === $o->id)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    @endauth

    {{-- An owner works across sites, so the site being edited is part of the
         chrome rather than a field buried on each form. Admins have one site
         and get a label instead of a control. --}}
    @auth
        @if ($user->isOwner() && ($ownerWebsites ?? collect())->count() > 1)
            <form class="tb-site" method="POST" action="{{ route('website.sites.switch') }}">@csrf
                <select name="website_id" onchange="this.form.submit()" aria-label="{{ __('Active website') }}">
                    @foreach ($ownerWebsites as $w)
                        <option value="{{ $w->id }}" @selected(($currentWebsite?->id) === $w->id)>{{ $w->name }}</option>
                    @endforeach
                </select>
            </form>
        @elseif ($currentWebsite)
            <span class="tb-role">{{ $currentWebsite->slug }}</span>
        @endif
    @endauth

    <form method="POST" action="{{ route('settings.theme') }}">@csrf
        <button class="tb-btn" type="submit" title="{{ __('Toggle theme') }}" aria-label="{{ __('Toggle theme') }}">
            @if (($appTheme ?? 'light') === 'dark')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"/>
                    <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                </svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
            @endif
        </button>
    </form>

    <span class="tb-sep"></span>

    @auth
        <a class="tb-user" href="{{ route('settings.edit') }}" title="{{ __('Your settings') }}">
            <span class="tb-avatar">{{ $user->initial() }}</span>
            <span class="tb-username">{{ $user->username }}</span>
        </a>
        <span class="tb-role">{{ $user->roleLabel() }}</span>
    @else
        <a class="tb-user" href="{{ route('login') }}">{{ __('Sign in') }}</a>
    @endauth
</header>

{{-- ── Sidebar ─────────────────────────────────────────────────────────────── --}}
<aside class="sb" id="sb">
    <div class="sb-scroll">
        <nav class="sb-nav">
            @foreach ($sections as $s)
                <a class="sb-item @if ($s['on']) on @endif" href="{{ $s['href'] }}" data-tip="{{ $s['label'] }}">
                    <span class="sb-icon">{!! $s['icon'] !!}</span>
                    <span class="sb-label">{{ $s['label'] }}</span>
                    @if ($s['id'] === 'messages' && ($navUnreadMessages ?? 0) > 0)
                        <span class="sb-badge">{{ $navUnreadMessages }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    <div class="sb-footer">
        @foreach ($footerNav as $s)
            <a class="sb-item @if ($s['on']) on @endif" href="{{ $s['href'] }}" data-tip="{{ $s['label'] }}"
               @if ($s['id'] === 'preview') target="_blank" rel="noopener" @endif>
                <span class="sb-icon">{!! $s['icon'] !!}</span>
                <span class="sb-label">{{ $s['label'] }}</span>
            </a>
        @endforeach

        @auth
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="sb-item logout" type="submit" data-tip="{{ __('Sign out') }}">
                    <span class="sb-icon">{!! \App\Support\Nav::ICON['logout'] !!}</span>
                    <span class="sb-label">{{ __('Sign out') }}</span>
                </button>
            </form>
        @endauth
    </div>
</aside>
<div class="sb-scrim" aria-hidden="true"></div>

{{-- Sub-rail: the sections of whichever module you are in. Rendered only when
     the module declares sections, so a single-page module does not get an
     empty second column. --}}
@if (! empty($moduleSections))
    <aside class="sb2" aria-label="{{ $moduleLabel }}">
        <div class="sb2-head">{{ $moduleLabel }}</div>
        <nav class="sb2-nav">
            @foreach ($moduleSections as $s)
                <a class="sb2-item @if ($s['on']) on @endif" href="{{ $s['href'] }}">{{ $s['label'] }}</a>
            @endforeach
        </nav>
    </aside>
@endif

<div class="shell @if (! empty($moduleSections)) has-sb2 @endif">
    <main class="wrap">
        @if (session('status'))
            <div class="flash ok">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash bad">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash bad">
                <strong>{{ trans_choice(':count problem with that|:count problems with that', $errors->count(), ['count' => $errors->count()]) }}</strong>
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
