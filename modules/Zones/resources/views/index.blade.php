@extends('layouts.app')
@section('title', __('Zones'))

@section('content')
    <h1>{{ __('Zones') }}</h1>
    <p class="sub">{{ $organization?->name }} — {{ __('Drawn areas, and what is zoned to them.') }}</p>

    <div class="card" style="margin-bottom:16px">
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search zones…') }}" style="flex:1;min-width:200px">
            <button class="btn" type="submit">{{ __('Search') }}</button>
            @if ($mayAdd)
                <a class="btn" href="{{ route('zones.records.create') }}">{{ __('Add zone') }}</a>
            @endif
        </form>
    </div>

    <div class="card table-wrap">
        <table>
            <tr>
                <th>{{ __('Zone') }}</th>
                <th>{{ __('Code') }}</th>
                <th>{{ __('Area (km²)') }}</th>
                <th>{{ __('Corners') }}</th>
                <th>{{ __('Zoned to') }}</th>
                <th>{{ __('Status') }}</th>
            </tr>
            @forelse ($zones as $zone)
                <tr>
                    <td>
                        <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $zone->colour }};margin-right:6px"></span>
                        <a href="{{ route('zones.records.edit', $zone) }}">{{ $zone->name }}</a>
                    </td>
                    <td>{{ $zone->code ?: '—' }}</td>
                    <td>{{ $zone->isDrawn() ? number_format($zone->approximateAreaKm2(), 1) : '—' }}</td>
                    <td>{{ count($zone->ring()) ?: '—' }}</td>
                    <td class="small">
                        @php $held = $counts[$zone->id] ?? []; @endphp
                        @forelse ($held as $type => $total)
                            {{ $type }}: {{ $total }}@if (! $loop->last), @endif
                        @empty
                            <span class="dim">{{ __('Nothing yet') }}</span>
                        @endforelse
                    </td>
                    <td>{{ $zone->active ? __('In service') : __('Withdrawn') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="dim">{{ __('No zones drawn yet.') }}</td></tr>
            @endforelse
        </table>
    </div>

    {{ $zones->links() }}
@endsection
