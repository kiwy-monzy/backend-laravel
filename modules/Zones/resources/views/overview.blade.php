@extends('layouts.app')
@section('title', __('Zones'))

@section('content')
    <h1>{{ __('Zones') }}</h1>
    <p class="sub">{{ $organization?->name }} — {{ __('Every area this organization has drawn, and what it covers.') }}</p>

    <div class="grid c3">
        <a class="stat" href="{{ route('zones.records.index') }}">
            <div class="n">{{ number_format($zones->count()) }}</div>
            <div class="k">{{ __('Zones') }}</div>
        </a>
        <div class="stat"><div class="n">{{ number_format($drawn) }}</div><div class="k">{{ __('Drawn') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($covered, 1) }}</div><div class="k">{{ __('km² covered') }}</div></div>
        <div class="stat"><div class="n">{{ number_format($inactive) }}</div><div class="k">{{ __('Withdrawn') }}</div></div>
    </div>

    <div class="card" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('Coverage') }}</h2>
        <div id="zones-map" style="height:460px;border-radius:10px;overflow:hidden"></div>
    </div>

    <div class="card table-wrap" style="margin-top:16px">
        <h2 style="margin-top:0">{{ __('What is zoned') }}</h2>
        <table>
            <tr><th>{{ __('Record type') }}</th><th>{{ __('Meaning') }}</th><th>{{ __('Zoned') }}</th></tr>
            @forelse ($byType as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>{{ $row['role'] }}</td>
                    <td>{{ number_format($row['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="dim">{{ __('Nothing has been zoned yet.') }}</td></tr>
            @endforelse
        </table>
        <p class="small dim">
            {{ __('Providers carry the areas they travel to, shipments the area they are going to, and the organization the areas it trades in.') }}
        </p>
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    'use strict';

    var ZONES  = @json($shapes);
    var CENTRE = @json($centre);
    var ZOOM   = @json($zoom);

    var map = L.map('zones-map', { center: [CENTRE.lat, CENTRE.lng], zoom: ZOOM });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    var drawn = [];

    ZONES.forEach(function (zone) {
        var shape = L.polygon(zone.ring, {
            color: zone.colour || '#2f6f4e',
            weight: 2,
            fillColor: zone.colour || '#2f6f4e',
            fillOpacity: zone.active ? 0.25 : 0.04,
            dashArray: zone.active ? null : '5 4',
        }).addTo(map).bindTooltip(zone.name);

        drawn.push(shape);
    });

    if (drawn.length) {
        map.fitBounds(L.featureGroup(drawn).getBounds(), { padding: [24, 24] });
    }
})();
</script>
@endpush
