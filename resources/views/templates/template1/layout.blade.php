<!DOCTYPE html>
<html lang="{{ $activeLocale ?? 'en' }}">
<head>
    @include('templates._head')
</head>
{{--
    Classic: gradient page, translucent sticky navbar, full-bleed hero — the original FGE design.

    The page's sections come from templates/_page.blade.php, which every
    template shares — this file decides only the shell around them.
--}}
<body class="t1 @if ($embedded ?? false) embedded @endif">
    @include('templates._splash')

    @include('templates._nav')

    <main>
        @include('templates._page', ['variant' => 'template1'])
    </main>

    @include('templates._footer')
</body>
</html>
