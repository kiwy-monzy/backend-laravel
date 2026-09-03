<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} · FGE</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/app.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::v('css/admin.css') }}">
</head>
{{-- No chrome: there is no sidebar to show someone who is not signed in, and
     rendering the shell here would mean every nav helper needing a null user. --}}
<body style="display:grid;place-items:center;min-height:100vh;padding:20px">
<main style="width:100%;max-width:380px">
    <h1 style="text-align:center">{{ __('FGE Admin') }}</h1>
    <p class="sub" style="text-align:center">{{ __('Sign in to manage your website.') }}</p>

    @if ($errors->any())
        <div class="flash bad">
            <ul style="margin:0 0 0 18px">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="card">
        @csrf

        <label>
            <span>{{ __('Username') }}</span>
            <input type="text" name="username" value="{{ old('username') }}" autocomplete="username" autofocus required>
        </label>

        <label>
            <span>{{ __('Password') }}</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <label style="display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="remember" value="1" style="width:auto">
            <span style="margin:0">{{ __('Keep me signed in') }}</span>
        </label>

        <button class="btn" type="submit" style="width:100%;justify-content:center">{{ __('Sign in') }}</button>
    </form>

    <p class="dim small" style="text-align:center;margin-top:14px">
        <a href="{{ route('site.root') }}">{{ __('Back to the website') }}</a>
    </p>
</main>
</body>
</html>
