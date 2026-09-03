@extends('layouts.app')
@section('title', __('Journal'))

@section('content')
    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:12px">
        <div>
            <h1 style="margin:0">{{ __('Journal') }}</h1>
            <p class="sub" style="margin:2px 0 0">{{ $organization?->name }}</p>
        </div>
        @if ($mayAdd)
            <a class="btn" href="{{ route('accounting.journal.create') }}">{{ __('New entry') }}</a>
        @endif
    </div>

    <div class="card" style="padding:10px">
        <div data-grid
             data-src="{{ route('accounting.journal.data') }}"
             data-columns='@json($gridColumns)'
             data-row-href="{{ route('accounting.journal.edit', ['entry' => '__ID__']) }}"
             data-per-page="100"
             data-empty="{{ __('Nothing posted yet.') }}"></div>
    </div>
@endsection
