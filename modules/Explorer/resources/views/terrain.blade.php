@extends('layouts.app')
@section('title', __('Terrain model'))

@section('content')
    <div class="explorer-head" style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:12px">
        <div>
            <h1 style="margin:0">{{ __('Dar es Salaam — Terrain (DEM)') }}</h1>
            <p class="sub" style="margin:2px 0 0">{{ __('Digital elevation model from the mapmap scene') }}</p>
        </div>
        <a class="btn small ghost" href="{{ route('explorer.index') }}">{{ __('← Network map') }}</a>
    </div>

    <div class="card">
        @if ($inStorage)
            <p class="dim small" style="margin-top:0">{{ __('Served from this organization’s storage — uploads/:id/map/', ['id' => \Illuminate\Support\Str::limit($organization?->id, 8, '…')]) }}</p>
        @else
            <p class="dim small" style="margin-top:0">{{ __('Served from the app bundle.') }}</p>
        @endif
        <img src="{{ $demUrl }}" alt="{{ __('Dar es Salaam digital elevation model') }}"
             style="width:100%;max-width:900px;border-radius:10px;border:1px solid var(--line);image-rendering:auto">
    </div>
@endsection
