@extends('layouts.app')
@section('title', __('Map Explorer'))
@section('body_class', 'explorer-full')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@section('content')
    <div class="explorer-head">
        <div>
            <h1 style="margin:0">{{ __('Tanzania Road Network') }}</h1>
            <p class="sub" style="margin:2px 0 0">{{ __('TANROADS trunk & regional corridors on OpenStreetMap · measured traffic and axle loading') }}</p>
        </div>
        <a class="btn small ghost" href="{{ route('explorer.terrain') }}">{{ __('Terrain model →') }}</a>
    </div>

    <div class="explorer-wrap">
        <div id="map" class="explorer-map" role="application" aria-label="{{ __('Tanzania road network map') }}"></div>

        <div class="explorer-panels">
            <section class="xp">
                <h2 class="xp-t">{{ __('Network at a glance') }}</h2>
                <dl class="xp-stats">
                    <div><dt>{{ __('km total') }}</dt><dd>{{ number_format($stats['km_tot'] ?? 0) }}</dd></div>
                    <div><dt>{{ __('regions') }}</dt><dd class="hot">{{ $stats['regions'] ?? 0 }}</dd></div>
                    <div><dt>{{ __('trunk links') }}</dt><dd>{{ number_format($stats['trunk'] ?? 0) }}</dd></div>
                    <div><dt>{{ __('regional links') }}</dt><dd>{{ number_format($stats['regional'] ?? 0) }}</dd></div>
                    <div><dt>{{ __('junction nodes') }}</dt><dd>{{ number_format($stats['nodes'] ?? 0) }}</dd></div>
                    <div><dt>{{ __('traffic-counted') }}</dt><dd>{{ number_format($stats['aadt_n'] ?? 0) }}</dd></div>
                </dl>
            </section>

            <section class="xp">
                <h2 class="xp-t">{{ __('Colour roads by') }}</h2>
                <div class="xp-modes" id="mode" role="group">
                    <button type="button" data-m="plain" aria-pressed="true">{{ __('Asphalt') }}</button>
                    <button type="button" data-m="aadt" aria-pressed="false">{{ __('Traffic') }}</button>
                    <button type="button" data-m="esal" aria-pressed="false">{{ __('Axle load') }}</button>
                </div>
                <label class="xp-check"><input type="checkbox" id="showNodes"> {{ __('Junction nodes') }}</label>
                <div id="legend" class="xp-legend"></div>
            </section>

            <section class="xp xp-grow">
                <h2 class="xp-t">
                    {{ __('Measured corridors · AADT') }}
                    <span class="xp-count" id="corrCount">—</span>
                </h2>
                <input type="search" id="corrSearch" class="xp-search" placeholder="{{ __('Filter by road or region…') }}" aria-label="{{ __('Filter corridors') }}">
                <ul class="xp-list" id="corr"><li class="xp-empty">{{ __('Loading the network…') }}</li></ul>
            </section>
        </div>
    </div>

    <p class="explorer-src">
        {{ __('Source: TANROADS ArcGIS — Nodes, Trunk_Roads_2022, Regional_Roads_2022 · no personal data.') }}
        {{ __('Basemap © OpenStreetMap contributors.') }}
    </p>
@endsection

@push('scripts')
    <style>
        body.explorer-full .content { max-width:none; }
        .explorer-head { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; margin-bottom:12px; }
        .explorer-wrap { display:grid; grid-template-columns:minmax(0,1fr) 310px; gap:14px; align-items:stretch; }
        .explorer-map { height:calc(100vh - 210px); min-height:520px; border:1px solid var(--line); border-radius:10px; background:var(--panel); }
        .explorer-panels { display:flex; flex-direction:column; gap:12px; max-height:calc(100vh - 210px); }
        .explorer-src { margin:10px 0 0; font-size:12px; color:var(--dim); }

        .xp { border:1px solid var(--line); border-radius:10px; background:var(--panel); padding:12px 13px; box-shadow:var(--shadow); }
        .xp-grow { flex:1; min-height:0; display:flex; flex-direction:column; }
        .xp-t { margin:0 0 9px; font-size:11px; letter-spacing:.09em; text-transform:uppercase; color:var(--dim);
                display:flex; justify-content:space-between; align-items:baseline; gap:8px; font-weight:600; }
        .xp-count { color:var(--accent); font-weight:700; letter-spacing:0; }

        .xp-stats { display:grid; grid-template-columns:1fr 1fr; gap:9px 12px; margin:0; }
        .xp-stats div { margin:0 }
        .xp-stats dd { margin:0; font-size:20px; font-weight:700; line-height:1.1; font-variant-numeric:tabular-nums; }
        .xp-stats dd.hot { color:var(--bad); }
        .xp-stats dt { order:2; font-size:11px; color:var(--dim); }
        .xp-stats div { display:flex; flex-direction:column; }

        .xp-modes { display:flex; gap:4px; background:var(--bg); border:1px solid var(--line); border-radius:8px; padding:3px; }
        .xp-modes button { flex:1; border:0; background:none; color:var(--dim); font:inherit; font-size:12px;
                           padding:5px 4px; border-radius:6px; cursor:pointer; }
        .xp-modes button[aria-pressed="true"] { background:var(--ink); color:var(--bg); font-weight:600; }
        .xp-check { display:flex; align-items:center; gap:7px; margin-top:9px; font-size:12px; color:var(--dim); cursor:pointer; }
        .xp-legend { margin-top:9px; font-size:11px; color:var(--dim); }
        .xp-legend .lrow { display:flex; align-items:center; gap:7px; margin-top:4px; }
        .xp-legend .sw { width:20px; height:0; border-top:3px solid; border-radius:2px; }
        .xp-legend .ramp { height:8px; border-radius:4px; flex:1; }
        .xp-legend .rlab { display:flex; justify-content:space-between; margin-top:3px; font-size:10px; }

        .xp-search { width:100%; box-sizing:border-box; padding:6px 9px; font:inherit; font-size:12px;
                     border:1px solid var(--line); border-radius:7px; background:var(--bg); color:var(--ink); }
        .xp-list { list-style:none; margin:8px 0 0; padding:0; overflow-y:auto; flex:1; min-height:0; }
        .xp-list li { display:flex; align-items:baseline; gap:7px; padding:6px 5px; border-radius:6px;
                      font-size:12px; cursor:pointer; border:1px solid transparent; }
        .xp-list li:hover { background:var(--bg); }
        .xp-list li[aria-selected="true"] { border-color:var(--accent); background:var(--bg); }
        .xp-list .rk { color:var(--dim); font-size:10px; min-width:20px; font-variant-numeric:tabular-nums; }
        .xp-list .tag { font-size:9px; font-weight:700; padding:1px 4px; border-radius:3px; background:var(--ink); color:var(--bg); }
        .xp-list .tag.R { background:var(--dim); }
        .xp-list .nm { flex:1; min-width:0; }
        .xp-list .nm small { display:block; color:var(--dim); font-size:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .xp-list .v { font-weight:700; font-variant-numeric:tabular-nums; }
        .xp-list .xp-empty { color:var(--dim); cursor:default; }

        /* Leaflet's own chrome, in the admin's colours. */
        .leaflet-container { background:var(--bg); font:inherit; border-radius:10px; }
        .leaflet-bar a, .leaflet-control-attribution { background:var(--panel); color:var(--ink); }
        .leaflet-bar a:hover { background:var(--bg); }
        .leaflet-control-attribution a { color:var(--accent); }
        [data-theme="dark"] .leaflet-tile-pane { filter:invert(1) hue-rotate(180deg) brightness(.85) contrast(.9); }
        .xroad-tip { font-size:12px; }
        .xroad-tip b { font-variant-numeric:tabular-nums; }

        @media (max-width:1100px) {
            .explorer-wrap { grid-template-columns:minmax(0,1fr); }
            .explorer-panels { max-height:none; }
            .explorer-map { height:60vh; }
        }
    </style>

    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>
    (function () {
        'use strict';

        var NETWORK_URL = @json($networkUrl);
        var fmt = function (n) { return (n == null) ? '—' : Number(n).toLocaleString('en-US'); };

        var map = L.map('map', { center: [-6.4, 35.0], zoom: 6, zoomControl: true, preferCanvas: true });

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        var roadPane = map.createPane('roads');
        roadPane.style.zIndex = 450;
        var nodePane = map.createPane('nodes');
        nodePane.style.zIndex = 460;

        var AADT_STOPS = [[1, '#8fb0c4'], [500, '#e7c15a'], [3000, '#e0812e'], [12000, '#c0431f'], [47186, '#7c1f1a']];
        var ESAL_STOPS = [[1, '#9fb8d6'], [2000, '#8f79c0'], [20000, '#7b3fa0'], [200000, '#5a1d78'], [3000000, '#31103f']];
        var PLAIN_TRUNK = '#1f2933';
        var PLAIN_REGIONAL = '#94a3b8';
        var NO_DATA = '#b9c2cc';

        function mix(a, b, t) {
            a = parseInt(a.slice(1), 16); b = parseInt(b.slice(1), 16);
            var ar = a >> 16, ag = (a >> 8) & 255, ab = a & 255;
            var br = b >> 16, bg = (b >> 8) & 255, bb = b & 255;
            return 'rgb(' + ((ar + (br - ar) * t) | 0) + ',' + ((ag + (bg - ag) * t) | 0) + ',' + ((ab + (bb - ab) * t) | 0) + ')';
        }

        function ramp(stops, val) {
            if (val == null || val <= 0) return null;
            var lo = stops[0][0], hi = stops[stops.length - 1][0];
            var v = Math.max(lo, Math.min(hi, val)), lv = Math.log(v);
            for (var i = 0; i < stops.length - 1; i++) {
                var a = stops[i], b = stops[i + 1];
                if (v <= b[0]) {
                    var t = (lv - Math.log(a[0])) / (Math.log(b[0]) - Math.log(a[0]));
                    return mix(a[1], b[1], Math.max(0, Math.min(1, t)));
                }
            }
            return stops[stops.length - 1][1];
        }

        var mode = 'plain';
        var DATA = null, layers = [], nodeLayer = null, selected = null, selectedLine = null;

        function styleFor(link) {
            var trunk = link.k === 'T';
            if (mode === 'plain') {
                return { color: trunk ? PLAIN_TRUNK : PLAIN_REGIONAL, weight: trunk ? 2.4 : 1.4, opacity: trunk ? 0.95 : 0.8 };
            }
            var colour = ramp(mode === 'aadt' ? AADT_STOPS : ESAL_STOPS, mode === 'aadt' ? link.a : link.e);
            if (!colour) return { color: NO_DATA, weight: trunk ? 1.6 : 1.0, opacity: 0.55 };
            return { color: colour, weight: trunk ? 3.4 : 2.4, opacity: 0.95 };
        }

        // The data stores [lng, lat]; Leaflet wants [lat, lng].
        function latlngs(coords) {
            var out = new Array(coords.length);
            for (var i = 0; i < coords.length; i++) out[i] = [coords[i][1], coords[i][0]];
            return out;
        }

        function tooltipFor(link) {
            var traffic = link.a != null ? fmt(link.a) + ' veh/day' : 'not counted';
            var load = (link.e != null && link.e > 0) ? fmt(Math.round(link.e)) + ' ESAL' : '—';
            var name = link.n || ((link.s && link.d) ? (link.s + ' – ' + link.d) : 'Road link');
            return '<div class="xroad-tip"><b>' + name + '</b><br>' +
                (link.k === 'T' ? 'Trunk' : 'Regional') + ' · ' + (link.r || '—') + ' · ' + link.km + ' km<br>' +
                'Traffic <b>' + traffic + '</b><br>Pavement load <b>' + load + '</b></div>';
        }

        function drawNetwork() {
            var all = DATA.regional.concat(DATA.trunk); // regional first, so trunk sits on top
            for (var i = 0; i < all.length; i++) {
                var link = all[i];
                var line = L.polyline(latlngs(link.c), Object.assign({ pane: 'roads', interactive: true }, styleFor(link)));
                line.bindTooltip(tooltipFor(link), { sticky: true, direction: 'top' });
                line._link = link;
                line.addTo(map);
                layers.push(line);
            }
            map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [18, 18] });
        }

        function restyle() {
            for (var i = 0; i < layers.length; i++) layers[i].setStyle(styleFor(layers[i]._link));
            if (selectedLine) selectedLine.setStyle({ color: '#e8a33d', weight: 6, opacity: 1 });
        }

        function toggleNodes(on) {
            if (!on) {
                if (nodeLayer) { map.removeLayer(nodeLayer); nodeLayer = null; }
                return;
            }
            var dots = DATA.nodes.map(function (n) {
                return L.circleMarker([n[1], n[0]], {
                    pane: 'nodes', radius: 2.2, weight: 0, fillColor: '#e8a33d', fillOpacity: 0.9, interactive: false,
                });
            });
            nodeLayer = L.layerGroup(dots).addTo(map);
        }

        // ── Corridor list ─────────────────────────────────────────────────
        var listEl = document.getElementById('corr');
        var countEl = document.getElementById('corrCount');

        function renderList(query) {
            var q = (query || '').trim().toLowerCase();
            var all = DATA.corridors;
            var shown = q ? all.filter(function (c) {
                return ((c.n || '') + ' ' + (c.r || '') + ' ' + (c.s || '') + ' ' + (c.d || '')).toLowerCase().indexOf(q) !== -1;
            }) : all;

            countEl.textContent = (q ? shown.length + ' / ' : '') + all.length;

            if (!shown.length) {
                listEl.innerHTML = '<li class="xp-empty">No corridor matches that filter.</li>';
                return;
            }

            listEl.innerHTML = shown.map(function (c) {
                var i = all.indexOf(c);
                var seg = (c.s && c.d) ? (c.s + ' → ' + c.d) : '';
                return '<li data-i="' + i + '" tabindex="0" role="button"' + (selected === c ? ' aria-selected="true"' : '') + '>' +
                    '<span class="rk">' + (i + 1) + '</span>' +
                    '<span class="tag ' + c.k + '">' + c.k + '</span>' +
                    '<span class="nm">' + (c.n || seg || '—') +
                    '<small>' + (c.r || '') + (seg && c.n ? ' · ' + seg : '') + '</small></span>' +
                    '<span class="v">' + fmt(c.a) + '</span></li>';
            }).join('');
        }

        function select(corridor) {
            selected = corridor;

            if (selectedLine) { map.removeLayer(selectedLine); selectedLine = null; }

            selectedLine = L.polyline(latlngs(corridor.c), {
                pane: 'nodes', color: '#e8a33d', weight: 6, opacity: 1, interactive: false,
            }).addTo(map);

            map.flyToBounds(selectedLine.getBounds(), { padding: [70, 70], maxZoom: 14, duration: 0.7 });
        }

        function pick(li) {
            if (!li || li.dataset.i === undefined) return;
            Array.prototype.forEach.call(listEl.children, function (x) { x.removeAttribute('aria-selected'); });
            li.setAttribute('aria-selected', 'true');
            select(DATA.corridors[+li.dataset.i]);
        }

        listEl.addEventListener('click', function (e) { pick(e.target.closest('li')); });
        listEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(e.target.closest('li')); }
        });
        document.getElementById('corrSearch').addEventListener('input', function (e) { renderList(e.target.value); });

        // ── Legend and mode ───────────────────────────────────────────────
        function legend() {
            var el = document.getElementById('legend');

            if (mode === 'plain') {
                el.innerHTML =
                    '<div class="lrow"><span class="sw" style="border-color:' + PLAIN_TRUNK + '"></span>Trunk road</div>' +
                    '<div class="lrow"><span class="sw" style="border-color:' + PLAIN_REGIONAL + '"></span>Regional road</div>';
                return;
            }

            var stops = mode === 'aadt' ? AADT_STOPS : ESAL_STOPS;
            var gradient = stops.map(function (s, i) { return s[1] + ' ' + (i / (stops.length - 1) * 100) + '%'; }).join(',');
            el.innerHTML =
                '<div class="lrow"><div class="ramp" style="background:linear-gradient(90deg,' + gradient + ')"></div></div>' +
                '<div class="rlab"><span>low</span><span>' + (mode === 'aadt' ? 'vehicles / day' : 'axle load (ESAL)') + '</span><span>high</span></div>' +
                '<div class="lrow" style="margin-top:6px"><span class="sw" style="border-color:' + NO_DATA + '"></span>no data</div>';
        }

        document.getElementById('mode').addEventListener('click', function (e) {
            var button = e.target.closest('button');
            if (!button) return;
            mode = button.dataset.m;
            Array.prototype.forEach.call(this.children, function (x) { x.setAttribute('aria-pressed', String(x === button)); });
            legend();
            if (DATA) restyle();
        });

        document.getElementById('showNodes').addEventListener('change', function (e) {
            if (DATA) toggleNodes(e.target.checked);
        });

        legend();

        // ── Load ──────────────────────────────────────────────────────────
        fetch(NETWORK_URL, { headers: { Accept: 'application/json' } })
            .then(function (r) {
                if (!r.ok) throw new Error('network ' + r.status);
                return r.json();
            })
            .then(function (json) {
                DATA = json;
                if (!DATA.trunk || !DATA.trunk.length) {
                    listEl.innerHTML = '<li class="xp-empty">The road network asset is missing.</li>';
                    countEl.textContent = '0';
                    return;
                }
                drawNetwork();
                renderList('');
                if (document.getElementById('showNodes').checked) toggleNodes(true);
            })
            .catch(function (err) {
                listEl.innerHTML = '<li class="xp-empty">Could not load the road network.</li>';
                countEl.textContent = '0';
                console.error('[explorer]', err);
            });
    })();
    </script>
@endpush
