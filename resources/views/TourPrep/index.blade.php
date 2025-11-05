<!-- resources/views/tourprep/index.blade.php -->
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Préparation de tournées — {{ $agRef }} — {{ $date }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    <style>
        :root{ --ink:#111; --mut:#666; --line:#e5e7eb; --pill:#eef; --warn:#c1121f; --urgent:#b91c1c; --ok:#223; }
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:16px;color:var(--ink);}
        header.page{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:8px;}
        .controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        label{font-size:13px}
        button,select,input[type="date"]{padding:6px 10px;border:1px solid #ccc;border-radius:6px;background:#fff;cursor:pointer}
        #map{height:58vh;margin-top:10px;border:1px solid var(--line);border-radius:8px}

        /* colonnes tech : max 4 par ligne, alignées à gauche, sans étirer */
        #panels{
            margin-top:12px;
            display:flex; flex-wrap:wrap; gap:10px;
            justify-content:flex-start; align-content:flex-start;
        }
        .tech-card{
            flex:0 0 320px; max-width:320px;
            border:1px solid var(--line); border-radius:10px; padding:8px;
            font-size:13px; /* plus petit pour le bloc agent + infos */
        }
        .tech-header{display:flex;gap:8px;align-items:baseline;flex-wrap:wrap;margin:2px 0 6px}
        .tech-name{font-weight:700; font-size:14px}
        .pill{display:inline-block;padding:2px 6px;border-radius:999px;background:var(--pill);font-size:11px;color:var(--ok);}
        .duration{margin-left:6px;font-size:12px}
        .duration.warn{color:var(--warn);font-weight:700;}
        .badge-urgent{display:inline-block;margin-left:6px;padding:1px 5px;border-radius:6px;background:#fee2e2;color:#7f1d1d;font-weight:700;font-size:10px;}

        /* liste d’arrêts compacte */
        .stops{margin:0;padding-left:18px}
        .stops li{margin:6px 0; line-height:1.25}
        .mut{color:var(--mut)}

        /* détails repliables : “+” à droite de l’heure, en inline */
        details{display:inline}
        details summary{
            display:inline; cursor:pointer; user-select:none; font-size:13px; color:var(--mut);
            margin-left:6px;
        }
        details summary::marker{content:""}
        details summary::before{content:"+"; font-weight:700;}
        details[open] summary::before{content:"–";}
        details .kv{margin:6px 0 0 0;padding-left:8px;border-left:3px solid var(--line)}
        details .kv div{font-size:12px;margin:2px 0}
    </style>
</head>
<body>

<header class="page">
    <h2 style="margin:0">Tournées — {{ $agRef }} — {{ $date }}</h2>
    <form class="controls" method="get" action="{{ route('tournee.show') }}">
        <input type="hidden" name="agref" value="{{ $agRef }}">
        <label>Date <input type="date" name="date" value="{{ $date }}"></label>
        <label>Mode
            <select name="mode">
                <option value="fast" {{ $mode==='fast'?'selected':'' }}>Rapide (vol d’oiseau)</option>
                <option value="precise" {{ $mode==='precise'?'selected':'' }}>Précis (OSRM)</option>
            </select>
        </label>
        <label>Optimiser
            <select name="opt">
                <option value="0" {{ !$opt?'selected':'' }}>Non (ordre heure)</option>
                <option value="1" {{  $opt?'selected':'' }}>Oui (trajet court)</option>
            </select>
        </label>
        <button type="submit">Actualiser</button>
    </form>
</header>

@if(empty($techTours))
    <p>Aucun RDV trouvé pour ce jour et cette agence de référence.</p>
@else
    <div id="map"></div>

    <div id="panels">
        @foreach($techTours as $tour)
            @php
                $distanceKm = number_format(($tour['total']['dist_m'] ?? 0)/1000, 1, ',', ' ');
                $durationS  = max(0, $tour['total']['dur_s'] ?? 0);
                $over6h     = (bool)($tour['total']['over6h'] ?? false);
            @endphp

            <div class="tech-card">
                <div class="tech-header">
                    <span class="tech-name">Tech {{ $tour['tech'] }}</span>
                    <span class="pill">Distance ~ {{ $distanceKm }} km</span>
                    <span class="duration {{ $over6h ? 'warn' : '' }}">
            Durée trajet: {{ gmdate('H\hi', $durationS) }}{{ $over6h ? ' — ⚠ > 6h' : '' }}
          </span>
                </div>

                <ol class="stops">
                    @foreach($tour['stops'] as $stop)
                        <li>
                            <strong>{{ $stop['cp'] ?? '—' }} - {{ $stop['city'] ?? '—' }}</strong>
                            @if(!empty($stop['urgent'])) <span class="badge-urgent">URGENT</span> @endif
                            <span class="mut">({{ $stop['heure'] ?? '—' }})</span>

                            <!-- “+” à droite (inline), ouvre les détails -->
                            <details>
                                <summary title="Plus d’infos"></summary>
                                <div class="kv">
                                    <div><b>N° intervention</b> : {{ $stop['numint'] }}</div>
                                    <div><b>Rue</b> : {{ $stop['street'] ?: '—' }}</div>
                                    <div><b>Type</b> : {{ $stop['type'] ?: '—' }}</div>
                                    <div><b>Marque</b> : {{ $stop['brand'] ?: '—' }}</div>
                                    @if(!empty($stop['comment']))
                                        <div><b>Commentaire</b> : {{ $stop['comment'] }}</div>
                                    @endif
                                </div>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
@endif

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const agencyStart = {!! json_encode($start ?? null) !!};
    const technicianTours = {!! json_encode($techTours ?? []) !!};

    if (technicianTours.length){
        const map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);

        const bounds = [];
        const palette = ['#007AFF','#34C759','#FF9500','#AF52DE','#FF2D55','#5856D6','#FF3B30','#5AC8FA'];

        if (agencyStart){
            const m = L.marker([agencyStart.lat, agencyStart.lon], {title:'Agence'}).addTo(map);
            m.bindPopup('<b>Agence</b><br>'+(agencyStart.label||''));
            bounds.push([agencyStart.lat, agencyStart.lon]);
        }

        technicianTours.forEach((tour, tIndex) => {
            const color = palette[tIndex % palette.length];

            tour.stops.forEach((stop, i) => {
                const marker = L.circleMarker([stop.lat, stop.lon], {
                    radius:6, weight:2, color:color, fillOpacity:0.6
                }).addTo(map);
                const urgentTxt = stop.urgent ? ' — <b style="color:#b91c1c">URGENT</b>' : '';
                marker.bindPopup(
                    `<b>${tour.tech} #${i+1}</b>${urgentTxt}<br>`+
                    `${(stop.cp||'—')} - ${(stop.city||'—')}<br>`+
                    `Heure: ${stop.heure||'—'}`
                );
                bounds.push([stop.lat, stop.lon]);
            });

            if (tour.geometry && tour.geometry.type === 'LineString') {
                const latlngs = tour.geometry.coordinates.map(([lon,lat]) => [lat, lon]);
                L.polyline(latlngs, {weight:4, opacity:0.8, color:color}).addTo(map);
            } else {
                const latlngs = [];
                if (agencyStart){ latlngs.push([agencyStart.lat, agencyStart.lon]); }
                tour.stops.forEach(s => latlngs.push([s.lat, s.lon]));
                if (agencyStart){ latlngs.push([agencyStart.lat, agencyStart.lon]); }
                L.polyline(latlngs, {weight:2, opacity:0.7, color:color, dashArray:'4 6'}).addTo(map);
            }
        });

        if (bounds.length){ map.fitBounds(bounds, {padding:[20,20]}); }
    }
</script>
</body>
</html>
