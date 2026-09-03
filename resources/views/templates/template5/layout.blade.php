<!DOCTYPE html>
<html lang="{{ $activeLocale ?? 'en' }}">
<head>
    @include('templates._head')
</head>
{{--
    Compact: single column, no cards, tuned for slow connections.

    The page's sections come from templates/_page.blade.php, which every
    template shares — this file decides only the shell around them.
--}}
<body class="t5 @if ($embedded ?? false) embedded @endif">
    @include('templates._splash')

    @include('templates._nav')

    <main>
        @include('templates._page', ['variant' => 'template5'])
    </main>

    @include('templates._footer')
</body>
</html>
