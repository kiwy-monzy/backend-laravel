@extends('layouts.app')
@section('title', $zone->exists ? $zone->name : __('Add zone'))

@section('content')
    <h1>{{ $zone->exists ? $zone->name : __('Add zone') }}</h1>
    <p class="sub"><a href="{{ route('zones.records.index') }}">{{ __('Zones') }}</a></p>

    <form method="POST" id="zone-form"
          action="{{ $zone->exists ? route('zones.records.update', $zone) : route('zones.records.store') }}"
          class="card">
        @csrf
        @if ($zone->exists) @method('PUT') @endif

        <div class="row">
            <label>
                <span>{{ __('Zone name') }}</span>
                <input type="text" name="name" value="{{ old('name', $zone->name) }}" required maxlength="190">
            </label>
            <label>
                <span>{{ __('Colour') }}</span>
                <input type="color" name="colour" id="zone-colour" value="{{ old('colour', $zone->colour ?: '#2f6f4e') }}">
            </label>
            <label style="display:flex;gap:8px;align-items:center">
                <input type="checkbox" name="active" value="1" style="width:auto" @checked(old('active', $zone->active ?? true))>
                <span style="margin:0">{{ __('In service') }}</span>
            </label>
            <label style="flex-basis:100%">
                <span>{{ __('Description') }}</span>
                <textarea name="description" maxlength="2000">{{ old('description', $zone->description) }}</textarea>
            </label>
        </div>

        {{-- The drawn ring travels as JSON in this hidden field. --}}
        <input type="hidden" name="coordinates" id="zone-coordinates"
               value="{{ old('coordinates', $zone->isDrawn() ? json_encode($zone->ring()) : '') }}">

        @error('coordinates')
            <p class="small" style="color:var(--danger,#b3261e)">{{ $message }}</p>
        @enderror

        <div style="margin:12px 0 8px">
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
                <input type="search" id="zone-search" placeholder="{{ __('Search for a place…') }}"
                       style="flex:1;min-width:220px" autocomplete="off">
                <button type="button" class="btn" id="zone-search-go">{{ __('Find') }}</button>
                <button type="button" class="btn" id="zone-draw">{{ __('Draw') }}</button>
                <button type="button" class="btn" id="zone-undo">{{ __('Undo corner') }}</button>
                <button type="button" class="btn" id="zone-clear">{{ __('Clear') }}</button>
            </div>
            <div id="zone-results" class="small dim"></div>
            <div id="zone-map" style="height:460px;border-radius:10px;overflow:hidden"></div>
            <p class="small dim" id="zone-hint">
                {{ __('Click the map to drop corners. Drag a corner to move it. Three corners make a zone.') }}
            </p>
        </div>

        @if ($mayEdit)
            <button class="btn" type="submit">{{ $zone->exists ? __('Save') : __('Create') }}</button>
        @endif
    </form>

    @if ($zone->exists && $mayEdit)
        <form method="POST" action="{{ route('zones.records.destroy', $zone) }}" class="card" style="margin-top:16px"
              onsubmit="return confirm('{{ __('Delete this zone? Anything zoned to it loses that area.') }}')">
            @csrf @method('DELETE')
            <button class="btn" type="submit">{{ __('Delete zone') }}</button>
        </form>
    @endif
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script>
(function () {
    'use strict';

    var CENTRE   = @json($centre);
    var ZOOM     = @json($zoom);
    var COLOUR   = document.getElementById('zone-colour');
    var FIELD    = document.getElementById('zone-coordinates');
    var NEIGHBOURS_URL = @json($neighboursUrl);
    var SEARCH_URL     = @json($searchUrl);

    var map = L.map('zone-map', { center: [CENTRE.lat, CENTRE.lng], zoom: ZOOM });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // ---- the ring being edited -------------------------------------------
    var points = [];
    var polygon = null;
    var markers = [];
    var drawing = true;

    try {
        var existing = JSON.parse(FIELD.value || '[]');
        if (Array.isArray(existing)) {
            points = existing.map(function (p) { return [Number(p[0]), Number(p[1])]; });
        }
    } catch (e) { points = []; }

    function colour() { return COLOUR.value || '#2f6f4e'; }

    function persist() {
        FIELD.value = points.length ? JSON.stringify(points.map(function (p) {
            return [Number(p[0].toFixed(7)), Number(p[1].toFixed(7))];
        })) : '';
    }

    function redraw() {
        if (polygon) { map.removeLayer(polygon); polygon = null; }
        markers.forEach(function (m) { map.removeLayer(m); });
        markers = [];

        if (points.length >= 2) {
            polygon = L.polygon(points, {
                color: colour(), weight: 2, fillColor: colour(), fillOpacity: 0.25,
            }).addTo(map);
        }

        points.forEach(function (point, index) {
            var marker = L.circleMarker(point, {
                radius: 6, color: '#fff', weight: 2, fillColor: colour(), fillOpacity: 1,
            }).addTo(map);

            marker.on('mousedown', function () {
                map.dragging.disable();
                function move(e) { points[index] = [e.latlng.lat, e.latlng.lng]; persist(); redraw(); }
                function stop() {
                    map.off('mousemove', move); map.off('mouseup', stop); map.dragging.enable();
                }
                map.on('mousemove', move); map.on('mouseup', stop);
            });

            marker.on('dblclick', function (e) {
                L.DomEvent.stop(e);
                points.splice(index, 1); persist(); redraw();
            });

            markers.push(marker);
        });

        persist();
        hint();
    }

    function hint() {
        var el = document.getElementById('zone-hint');
        if (points.length === 0) {
            el.textContent = @json(__('Click the map to drop corners. Drag a corner to move it. Three corners make a zone.'));
        } else if (points.length < 3) {
            el.textContent = @json(__('Keep going — a zone needs at least three corners.'));
        } else {
            el.textContent = points.length + ' ' + @json(__('corners. Double-click a corner to remove it.'));
        }
    }

    map.on('click', function (e) {
        if (!drawing) { return; }
        points.push([e.latlng.lat, e.latlng.lng]);
        redraw();
    });

    COLOUR.addEventListener('input', redraw);

    document.getElementById('zone-draw').addEventListener('click', function () {
        drawing = !drawing;
        this.textContent = drawing ? @json(__('Drawing…')) : @json(__('Draw'));
    });

    document.getElementById('zone-undo').addEventListener('click', function () {
        points.pop(); redraw();
    });

    document.getElementById('zone-clear').addEventListener('click', function () {
        points = []; redraw();
    });

    // ---- the other zones, drawn behind ------------------------------------
    fetch(NEIGHBOURS_URL, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : { zones: [] }; })
        .then(function (data) {
            (data.zones || []).forEach(function (zone) {
                L.polygon(zone.coordinates, {
                    color: zone.colour || '#888', weight: 1, dashArray: '4 3',
                    fillColor: zone.colour || '#888', fillOpacity: 0.08, interactive: false,
                }).addTo(map).bindTooltip(zone.name);
            });
        })
        .catch(function () { });

    // ---- place search -----------------------------------------------------
    var searchBox = document.getElementById('zone-search');
    var results = document.getElementById('zone-results');

    function search() {
        var term = searchBox.value.trim();
        if (term.length < 3) { results.textContent = @json(__('Type at least three letters.')); return; }

        results.textContent = @json(__('Searching…'));

        fetch(SEARCH_URL + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { results.textContent = data.error; return; }
                if (!data.places || !data.places.length) { results.textContent = @json(__('Nothing found.')); return; }

                results.innerHTML = '';
                data.places.forEach(function (place) {
                    var link = document.createElement('a');
                    link.href = '#';
                    link.textContent = place.name;
                    link.style.display = 'block';
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (place.bounds) {
                            map.fitBounds([
                                [place.bounds.min_lat, place.bounds.min_lng],
                                [place.bounds.max_lat, place.bounds.max_lng],
                            ]);
                        } else {
                            map.setView([place.lat, place.lng], 15);
                        }
                        results.innerHTML = '';
                    });
                    results.appendChild(link);
                });
            })
            .catch(function () { results.textContent = @json(__('Place search is unavailable right now.')); });
    }

    document.getElementById('zone-search-go').addEventListener('click', search);

    searchBox.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); search(); }
    });

    document.getElementById('zone-form').addEventListener('submit', function (e) {
        if (points.length < 3) {
            e.preventDefault();
            results.textContent = @json(__('Draw the zone on the map first — it needs at least three corners.'));
        }
    });

    if (points.length) {
        redraw();
        map.fitBounds(L.polygon(points).getBounds(), { padding: [20, 20] });
    } else {
        hint();
    }
})();
</script>
@endpush
