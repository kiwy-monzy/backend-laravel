<!DOCTYPE html>
<html lang="{{ $activeLocale ?? 'en' }}">
<head>
    @include('templates._head')
</head>
{{--
    Campaign: split hero with the donate rail pinned beside it.

    The page's sections come from templates/_page.blade.php, which every
    template shares — this file decides only the shell around them.
--}}
<body class="t4 @if ($embedded ?? false) embedded @endif">
    @include('templates._splash')

    @include('templates._nav')

    <main>
        @include('templates._page', ['variant' => 'template4'])
    </main>

    @include('templates._footer')
</body>
</html>
