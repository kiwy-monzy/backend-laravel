@extends('layouts.app')
@section('title', $site->name)

@section('content')
    <h1>{{ $site->name }}</h1>
    <p class="sub">
        <a href="{{ route('website.index') }}">{{ __('Websites') }}</a> ·
        {{ $site->domain ?: '/s/' . $site->slug }} ·
        <span class="chip">{{ \App\Support\Templates::ALL[$site->templateKey()]['label'] }}</span>
        · <a href="{{ site_url($site, 'home') }}" target="_blank" rel="noopener">{{ __('View site') }}</a>
    </p>

    <div class="grid c4">
        <a class="stat" href="{{ route('website.gallery.index') }}">
            <div class="n">{{ number_format($counts['gallery']) }}</div>
            <div class="k">{{ __('Gallery images') }}</div>
        </a>
        <a class="stat" href="{{ route('website.donations.index') }}">
            <div class="n">{{ number_format($counts['donations']) }}</div>
            <div class="k">{{ __('Donations') }}</div>
        </a>
        <a class="stat" href="{{ route('website.volunteers.index') }}">
            <div class="n">{{ number_format($counts['volunteers']) }}</div>
            <div class="k">{{ __('Volunteers') }}</div>
        </a>
        <a class="stat @if ($counts['unread']) warn @endif" href="{{ route('website.messages.index') }}">
            <div class="n">{{ number_format($counts['unread']) }}</div>
            <div class="k">{{ __('Unread messages') }}</div>
        </a>
    </div>

    <div class="grid c2" style="margin-top:16px">
        <div class="card">
            <h2 style="margin-top:0">{{ __('Content') }}</h2>
            <p class="dim small">{{ __('An empty section renders as a gap on the public site.') }}</p>
            <table>
                @foreach ($sectionStatus as $key => $filled)
                    <tr>
                        <td>
                            <span class="dot {{ $filled ? 'on' : 'off' }}"></span>
                            <a href="{{ route('website.content.edit', $key) }}">{{ ucfirst($key) }}</a>
                        </td>
                        <td class="right-align dim small">{{ $filled ? __('filled') : __('empty') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>

        <div class="card">
            <h2 style="margin-top:0">
                {{ __('Gallery') }}
                <a class="small" style="font-weight:400" href="{{ route('website.gallery.index') }}">{{ __('manage') }}</a>
            </h2>

            @if ($gallery->isEmpty())
                <p class="dim">{{ __('No images on this site yet.') }}</p>
            @else
                <div class="media" style="grid-template-columns:repeat(auto-fill,minmax(110px,1fr))">
                    @foreach ($gallery as $image)
                        <div class="tile @if ($image->disabled) off @endif">
                            <img src="{{ $image->url }}" alt="{{ $image->caption }}" loading="lazy"
                                 style="height:80px">
                        </div>
                    @endforeach
                </div>
                <p class="dim small" style="margin-top:8px">
                    {{ __('Files in this website\'s collection.') }}
                </p>
            @endif
        </div>
    </div>
@endsection
