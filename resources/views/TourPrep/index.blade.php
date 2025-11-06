<!-- resources/views/tourprep/index.blade.php -->
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Préparation de tournées — {{ $agRef }} — {{ $date }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    <style>
        :root {
            --ink: #111;
            --mut: #666;
            --line: #e5e7eb;
            --pill: #eef;
            --warn: #c1121f;
            --urgent: #b91c1c;
            --ok: #223;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            margin: 16px;
            color: var(--ink);
        }

        header.page {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .controls {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap
        }

        label {
            font-size: 13px
        }

        button, select, input[type="date"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #fff;
            cursor: pointer
        }

        #map {
            height: 58vh;
            margin-top: 10px;
            border: 1px solid var(--line);
            border-radius: 8px
        }

        /* Colonnes tech : max 4 par ligne, alignées à gauche */
        #panels {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-start;
            align-content: flex-start;
        }

        .tech-card {
            flex: 0 0 320px;
            max-width: 320px;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px;
            font-size: 13px; /* plus petit pour ce bloc */
        }

        .tech-header {
            display: flex;
            gap: 8px;
            align-items: baseline;
            flex-wrap: wrap;
            margin: 2px 0 6px
        }

        .tech-name {
            font-weight: 700;
            font-size: 14px
        }

        .pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 999px;
            background: var(--pill);
            font-size: 11px;
            color: var(--ok);
        }

        .duration {
            margin-left: 6px;
            font-size: 12px
        }

        .duration.warn {
            color: var(--warn);
            font-weight: 700;
        }

        .badge-urgent {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 5px;
            border-radius: 6px;
            background: #fee2e2;
            color: #7f1d1d;
            font-weight: 700;
            font-size: 10px;
        }

        /* Liste d’arrêts compacte */
        .stops {
            margin: 0;
            padding-left: 18px
        }

        .stops li {
            margin: 6px 0;
            line-height: 1.25
        }

        /* Titre CP - Ville ellipsis + heure */
        .cp-city {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 700;
        }

        .hour {
            color: var(--mut)
        }

        .hour {
            color: var(--mut);
            flex: 0 0 auto;
        }


        /* Ligne d'entête d'une intervention : tout sur la même ligne */
        .stop-line {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }

        /* Zone actions à droite (reprogrammer + toggle) */
        .stop-mini-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            flex-shrink: 0;
            flex: 0 0 auto;
        }

        /* Contenu bas (reste dans la card) */
        .stop-extra {
            display: none;
            margin: 6px 0 0 0;
            padding-left: 8px;
            border-left: 3px solid var(--line)
        }

        .stop-extra div {
            font-size: 12px;
            margin: 2px 0
        }

        /* Boutons */
        .btn-mini {
            border: 1px solid #d1d5db;
            background: #fff;
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 6px;
            cursor: pointer
        }

        .btn-mini:hover {
            background: #f8fafc
        }

        .btn-toggle {
            width: 18px;
            height: 18px;
            display: inline-grid;
            place-items: center;
            font-weight: 700;
            font-size: 12px;
            line-height: 1;
        }

        /* Modale replanification (au-dessus de Leaflet) */
        .hidden {
            display: none
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .35);
            z-index: 10000;
        }

        .modal {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10010;
        }

        .modal .panel {
            width: min(700px, 96vw);
            max-height: 90vh;
            overflow: auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 14px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .modal .panel h3 {
            margin: 0 0 8px;
            font-size: 16px
        }

        .modal .panel label {
            display: block;
            margin: 8px 0 4px
        }

        .modal .panel input, .modal .panel select, .modal .panel textarea {
            width: 100%;
            max-width: 100%;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            box-sizing: border-box;
        }

        .modal .actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 12px
        }

        .modal .btn {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
            cursor: pointer
        }

        .modal .btn.primary {
            background: #111;
            color: #fff;
            border-color: #111
        }
        /* --- RDV temporaire (orangé) --- */
        .stop-line.is-temp{background:#fff7ed;border-left:3px solid #f59e0b;border-radius:6px;padding-left:6px;}
        .badge-temp{display:inline-block;margin-left:6px;padding:1px 6px;border-radius:6px;background:#FEF3C7;color:#92400E;font-weight:700;font-size:10px;border:1px solid #FDE68A}

        /* --- Segmented toggle Temp/Validé (dans la modale) --- */
        .segmented{display:inline-flex;border:1px solid #d1d5db;border-radius:8px;overflow:hidden}
        .segmented input.seg{position:absolute;left:-9999px}
        .segmented label{padding:6px 10px;cursor:pointer;font-size:13px;user-select:none}
        .segmented input.seg:checked + label{background:#111;color:#fff}
        .segmented label + input.seg + label{border-left:1px solid #d1d5db}

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
                        @php
                            $heureRaw = (string) data_get($stop, 'heure', '');
                            $heureAff = $heureRaw ? substr($heureRaw, 0, 5) : '—';
                            $extraId  = 'extra_'.preg_replace('~[^A-Za-z0-9_-]~', '_', (string) data_get($stop, 'numint', uniqid()));
                        @endphp


                        <li>
                            <div class="stop-line {{ (!empty($stop['has_temp']) && empty($stop['has_validated'])) ? 'is-temp' : '' }}">
                               <span class="cp-city"
                                     title="{{ data_get($stop,'cp','—').' - '.data_get($stop,'city','—') }}">
    {{ data_get($stop,'cp','—') }} - {{ data_get($stop,'city','—') }}
</span>

                                @if(!empty(data_get($stop,'urgent')))
                                    <span class="badge-urgent">URGENT</span>
                                @endif

                                <span class="hour">{{ $heureAff }}</span>

                                @php
                                    // astuce : fabriquer un numInt "factice" depuis l'agref si besoin, ex: "$agRef-0000-00000"
                                    $fakeForPeople = data_get($stop,'numint') ? data_get($stop,'numint') : ($agRef.'-0000-00000');
                                    $people = app(\App\Services\AccessInterventionService::class)
                                        ->listPeopleForNumInt($fakeForPeople)
                                        ->map(function($p){
                                            return [
                                                'code' => data_get($p, 'CodeSal'),
                                                'name' => data_get($p, 'NomSal'),
                                            ];
                                        })
                                        ->values();
                                @endphp


                                <div class="stop-mini-actions">
                                    <button type="button"
                                        class="btn-mini btn-replan"
                                        data-endpoint="{{ route('tournee.replanifier', ['numInt' => data_get($stop,'numint')]) }}"
                                        data-numint="{{ data_get($stop,'numint') }}"
                                        data-date="{{ $date }}"
                                        data-time="{{ $heureAff !== '—' ? $heureAff : '' }}"
                                        data-tech="{{ data_get($tour,'tech_code','') }}"
                                        data-people='@json($people)'
                                        data-has-temp="{{ !empty($stop['has_temp']) ? 1 : 0 }}"
                                        data-has-validated="{{ !empty($stop['has_validated']) ? 1 : 0 }}">
                                            Reprogrammer
                                    </button>

                                    <button type="button"
                                            class="btn-mini btn-toggle"
                                            aria-expanded="false"
                                            aria-controls="{{ $extraId }}">+
                                    </button>
                                </div>
                            </div>

                            <div id="{{ $extraId }}" class="stop-extra" role="region" aria-label="Détails intervention">
                                <div><b>Lieu</b> : {{ data_get($stop,'cp','—') }} - {{ data_get($stop,'city','—') }}</div>
                                <div><b>N° intervention</b> : {{ data_get($stop,'numint','') }}</div>
                                <div><b>Rue</b> : {{ data_get($stop,'street','—') }}</div>
                                <div><b>Type</b> : {{ data_get($stop,'type','—') }}</div>
                                <div><b>Marque</b> : {{ data_get($stop,'brand','—') }}</div>
                                @if(!empty(data_get($stop,'comment')))
                                    <div><b>Commentaire</b> : {{ data_get($stop,'comment') }}</div>
                                @endif
                                @if(!empty($stop['has_temp']) && empty($stop['has_validated']))
                                    <span class="badge-temp">RDV temporaire</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </div>
@endif

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    "use strict";

    /** Données côté serveur */
    const agencyStart      = {!! json_encode($start ?? null) !!};
    const technicianTours  = {!! json_encode($techTours ?? []) !!};

    (function initMap() {
        if (!Array.isArray(technicianTours) || technicianTours.length === 0) return;

        const map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution:'&copy; OpenStreetMap' }).addTo(map);

        const palette   = ['#007AFF','#34C759','#FF9500','#AF52DE','#FF2D55','#5856D6','#FF3B30','#5AC8FA'];
        const allBounds = [];

        // Agence
        if (agencyStart && typeof agencyStart.lat === 'number' && typeof agencyStart.lon === 'number') {
            const m = L.marker([agencyStart.lat, agencyStart.lon], { title: 'Agence' }).addTo(map);
            m.bindPopup(`<b>Agence</b><br>${agencyStart.label || ''}`);
            allBounds.push([agencyStart.lat, agencyStart.lon]);
        }

        function popupHtml(tour, index, stop) {
            const urgentBadge = stop.urgent ? ' — <b style="color:#b91c1c">URGENT</b>' : '';
            const cpCity = `${(stop.cp || '—')} - ${(stop.city || '—')}`;
            const heure  = (typeof stop.heure === 'string' && stop.heure.length >= 5) ? stop.heure.slice(0,5) : (stop.heure || '—');
            return `<b>${tour.tech} #${index + 1}</b>${urgentBadge}<br>${cpCity}<br>Heure: ${heure}`;
        }

        function drawRoute(color, tour) {
            if (tour.geometry && tour.geometry.type && tour.geometry.coordinates) {
                const type = tour.geometry.type;
                if (type === 'LineString') {
                    const latlngs = tour.geometry.coordinates.map(([lon, lat]) => [lat, lon]);
                    L.polyline(latlngs, { weight: 4, opacity: 0.85, color }).addTo(map);
                    latlngs.forEach(ll => allBounds.push(ll));
                    return;
                }
                if (type === 'MultiLineString') {
                    tour.geometry.coordinates.forEach(line => {
                        const latlngs = line.map(([lon, lat]) => [lat, lon]);
                        L.polyline(latlngs, { weight: 4, opacity: 0.85, color }).addTo(map);
                        latlngs.forEach(ll => allBounds.push(ll));
                    });
                    return;
                }
            }
            // Fallback “vol d’oiseau”
            const latlngs = [];
            if (agencyStart) { latlngs.push([agencyStart.lat, agencyStart.lon]); }
            (tour.stops || []).forEach(s => latlngs.push([s.lat, s.lon]));
            if (agencyStart) { latlngs.push([agencyStart.lat, agencyStart.lon]); }
            L.polyline(latlngs, { weight: 2, opacity: 0.75, color, dashArray: '4 6' }).addTo(map);
            latlngs.forEach(ll => allBounds.push(ll));
        }

        technicianTours.forEach((tour, tIndex) => {
            const color = palette[tIndex % palette.length];

            (tour.stops || []).forEach((stop, i) => {
                if (typeof stop.lat !== 'number' || typeof stop.lon !== 'number') return;
                const mk = L.circleMarker([stop.lat, stop.lon], { radius: 6, weight: 2, color, fillOpacity: 0.6 }).addTo(map);
                mk.bindPopup(popupHtml(tour, i, stop));
                allBounds.push([stop.lat, stop.lon]);
            });

            drawRoute(color, tour);
        });

        if (allBounds.length) {
            map.fitBounds(allBounds, { padding: [20, 20] });
        }
    })();

    /* ---------- Modale replanification ---------- */
    (function () {
        const wrapHTML = `
<div id="replanWrap" class="hidden">
  <div class="modal-backdrop"></div>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="rp_title">
    <div class="panel">
      <h3 id="rp_title">Reprogrammer le RDV</h3>
      <form id="rp_form">
        <input type="hidden" id="rp_endpoint" value="">
        <input type="hidden" id="rp_numint" value="">
        <input type="hidden" id="rp_has_temp" value="0">
        <input type="hidden" id="rp_has_validated" value="0">

        <label>Date du RDV
          <input type="date" id="rp_date" required>
        </label>
        <label>Heure du RDV
          <input type="time" id="rp_time" required>
        </label>

        <label>Technicien (code)
          <select id="rp_tech"></select>
        </label>

        <label>Commentaire
          <textarea id="rp_comment" rows="3" placeholder="(optionnel)"></textarea>
        </label>

        <label>Type de RDV</label>
        <div class="segmented" role="tablist" aria-label="Type de RDV">
          <input class="seg" type="radio" name="rp_mode" id="rp_mode_valide" value="valide" checked>
          <label for="rp_mode_valide">Validé</label>
          <input class="seg" type="radio" name="rp_mode" id="rp_mode_temp" value="temp">
          <label for="rp_mode_temp">Temporaire</label>
        </div>

        <div class="actions">
          <button type="button" class="btn" id="rp_cancel">Annuler</button>
          <button type="submit" class="btn primary">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
</div>`;
        document.body.insertAdjacentHTML('beforeend', wrapHTML);

        const wrap      = document.getElementById('replanWrap');
        const form      = document.getElementById('rp_form');
        const fEndpoint = document.getElementById('rp_endpoint');
        const fNumInt   = document.getElementById('rp_numint');
        const fDate     = document.getElementById('rp_date');
        const fTime     = document.getElementById('rp_time');
        const fTech     = document.getElementById('rp_tech');
        const fComment  = document.getElementById('rp_comment');
        const fHasTemp  = document.getElementById('rp_has_temp');
        const fHasVal   = document.getElementById('rp_has_validated');
        const btnCancel = document.getElementById('rp_cancel');

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function openModal({endpoint, numInt, date, time, tech, people, hasTemp='0', hasValidated='0'}) {
            fEndpoint.value = endpoint || '';
            fNumInt.value   = numInt   || '';
            fDate.value     = (date || '').slice(0,10);
            fTime.value     = (time || '').slice(0,5);

            // Remplir le select tech
            let arr = [];
            try { arr = JSON.parse(people || '[]'); } catch(_) {}
            fTech.innerHTML = '<option value="">— choisir —</option>';
            arr.forEach(p => {
                if (!p || !p.code) return;
                const opt = document.createElement('option');
                opt.value = p.code;
                opt.textContent = `${p.code} — ${p.name || ''}`;
                fTech.appendChild(opt);
            });
            if (tech) fTech.value = tech;

            // Mémorise l’état (pour la soumission)
            fHasTemp.value = String(hasTemp);
            fHasVal.value  = String(hasValidated);

            // Pré-sélection du toggle
            const rVal = document.getElementById('rp_mode_valide');
            const rTmp = document.getElementById('rp_mode_temp');
            if (hasValidated === '1')      { rVal.checked = true;  rTmp.checked = false; }
            else if (hasTemp === '1')      { rVal.checked = false; rTmp.checked = true;  }
            else                           { rVal.checked = true;  rTmp.checked = false; }

            fComment.value = '';
            wrap.classList.remove('hidden');
        }

        function closeModal() { wrap.classList.add('hidden'); }

        // Ouvrir la modale
        document.addEventListener('click', function(e){
            const btn = e.target.closest('.btn-replan');
            if (!btn) return;
            e.preventDefault();
            openModal({
                endpoint:      btn.dataset.endpoint      || '',
                numInt:        btn.dataset.numint        || '',
                date:          btn.dataset.date          || '',
                time:          btn.dataset.time          || '',
                tech:          btn.dataset.tech          || '',
                people:        btn.dataset.people        || '[]',
                hasTemp:       btn.dataset.hasTemp       || '0',
                hasValidated:  btn.dataset.hasValidated  || '0'
            });
        });

        // Fermer (fond / bouton)
        btnCancel.addEventListener('click', closeModal);
        wrap.addEventListener('click', function (e) {
            if (e.target === wrap || e.target.classList.contains('modal-backdrop')) closeModal();
        });

        // Soumission AJAX
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const endpoint = fEndpoint.value;
            if (!endpoint) { alert("Endpoint manquant"); return; }

            const rdvDate = fDate.value;
            const rdvTime = fTime.value;
            if (!rdvDate || !rdvTime) { alert("Date/heure requis."); return; }

            const tech = (fTech.value || '').trim();
            const mode = (document.querySelector('input[name="rp_mode"]:checked')?.value || 'valide');

            const hasTempFlag = (fHasTemp.value === '1');
            const hasValFlag  = (fHasVal.value  === '1');

            // —————— BRANCHE RDV TEMPORAIRE ——————
            if (mode === 'temp') {
                const url = fEndpoint.value.replace(/\/replanifier$/, '/rdv/temporaire');

                const basePayload = {
                    date_rdv:   rdvDate,
                    heure_rdv:  rdvTime,
                    rea_sal:    (tech || null),
                    commentaire:(fComment.value || '').trim() || null
                };

                async function postTemp(payload) {
                    const res  = await fetch(url, {
                        method:'POST',
                        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(()=>({}));
                    return { res, data };
                }

                let {res, data} = await postTemp(basePayload);

                // Conflit : un VALIDÉ existe déjà → confirmation, puis retry avec purge_validated=true
                if (res.status === 409 && (data.code === 'VALIDATED_EXISTS' || /VALIDATED_EXISTS/.test(data.msg||''))) {
                    const ok = window.confirm(
                        "Le rendez-vous est actuellement VALIDÉ .\n" +
                        "Souhaitez-vous le faire passer en TEMPORAIRE ?"
                    );
                    if (!ok) return;

                    const retry = await postTemp({ ...basePayload, purge_validated: true });
                    if (!retry.res.ok || retry.data.ok === false) {
                        const msg = retry.data.msg || retry.data.error || retry.data.message
                            || (retry.data.errors ? Object.values(retry.data.errors).flat().join('\n')
                                :  (retry.res.status + ' ' + retry.res.statusText));
                        alert("Échec replanification (temp) : " + msg);
                        console.error('[rdv.temporaire/confirm] payload =', { ...basePayload, purge_validated:true }, 'resp =', retry.data);
                        return;
                    }
                    closeModal(); location.reload();
                    return;
                }

                if (!res.ok || data.ok === false) {
                    const msg = data.msg || data.error || data.message
                        || (data.errors ? Object.values(data.errors).flat().join('\n')
                            :  (res.status + ' ' + res.statusText));
                    alert("Échec replanification (temp) : " + msg);
                    console.error('[rdv.temporaire] payload =', basePayload, 'resp =', data);
                    return;
                }

                closeModal(); location.reload();
                return;
            }

            // —————— BRANCHE RDV VALIDÉ ——————
            // S’il existe déjà un TEMP → confirmation avant d’envoyer
            if (mode === 'valide' && hasTempFlag) {
                const ok = window.confirm(
                    "Le rendez-vous est actuellement TEMPORAIRE.\n" +
                    "Voulez-vous le faire passer en RDV VALIDÉ ?"
                );
                if (!ok) return;
            }

            const payload = {
                rdv_at:    rdvDate + ' ' + rdvTime + ':00',
                tech_code: tech || null,
                comment:   (fComment.value || '').trim() || null
            };

            const res  = await fetch(fEndpoint.value, {
                method:'POST',
                headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': csrf, 'Accept':'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(()=>({}));

            if (!res.ok || data.ok === false) {
                const msg = data.msg || data.error || data.message
                    || (data.errors ? Object.values(data.errors).flat().join('\n')
                        :  (res.status + ' ' + res.statusText));
                alert("Échec replanification : " + msg);
                console.error('[replanifier] payload =', payload, 'resp =', data);
                return;
            }

            closeModal(); location.reload();
        });
    })();

    /* ---------- Toggle JS: + / – sans bouger la ligne ---------- */
    document.addEventListener('click', function (e) {
        const t = e.target.closest('.btn-toggle');
        if (!t) return;
        e.preventDefault();
        const id = t.getAttribute('aria-controls');
        const extra = document.getElementById(id);
        if (!extra) return;

        const expanded = t.getAttribute('aria-expanded') === 'true';
        t.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        t.textContent = expanded ? '+' : '–';
        extra.style.display = expanded ? 'none' : 'block';
    });
</script>


</body>
</html>
