@extends('layouts.app')
@section('title', __('Content'))

@section('content')
    <h1>{{ __('Content') }}</h1>
    <p class="sub">{{ __('The sections every template renders, for :site.', ['site' => $site?->name ?? '—']) }}</p>

    <div class="grid c3">
        @foreach ($sections as $s)
            <a class="card" href="{{ $s['profile'] ? route('organization.edit') : route('website.content.edit', $s['key']) }}" style="display:block">
                <strong>
                    <span class="dot {{ $s['profile'] ? 'on' : ($s['filled'] ? 'on' : 'off') }}"></span>{{ $s['label'] }}
                </strong>
                <div class="dim small" style="margin-top:4px">
                    @if ($s['profile'])
                        {{ __('Edited on the Organization profile') }}
                    @elseif ($s['filled'])
                        {{ __('Has content') }}
                    @else
                        {{ __('Empty — hidden on the public site') }}
                    @endif
                </div>
            </a>
        @endforeach
    </div>
@endsection
