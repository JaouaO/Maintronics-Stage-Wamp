<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Préparation de tournées — {{ $agRef }} — {{ $date }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Leaflet --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

    {{-- Styles page --}}
    <link rel="stylesheet" href="{{ asset('css/tourprep.css') }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="autoplan-generate" content="{{ route('tourprep.autoplan.generate', [], false) }}">
    <meta name="autoplan-commit"   content="{{ route('tourprep.autoplan.commit',   [], false) }}">
</head>
<body>

<header class="page">
    <h2 style="margin:0">Tournées — {{ $agRef }} — {{ $date }}</h2>

    <form class="controls" method="get" action="{{ route('tournee.show') }}">
        <input type="hidden" name="agref" value="{{ $agRef }}">
        <label>Date <input type="date" name="date" value="{{ $date }}"></label>
        <label>Mode
            <select name="mode">
                <option value="fast" {{ $mode==='fast'?'selected':'' }}>Rapide</option>
                <option value="precise" {{ $mode==='precise'?'selected':'' }}>Précis (OSRM)</option>
            </select>
        </label>
        <label>Optimiser
            <select name="opt">
                <option value="0" {{ !$opt?'selected':'' }}>Non</option>
                <option value="1" {{  $opt?'selected':'' }}>Oui</option>
            </select>
        </label>
        <button type="submit">Actualiser</button>

        {{-- Bouton Autoplanning --}}
        <button id="btnAutoplan"
                type="button"
                data-date="{{ $date }}"
                data-agref="{{ $agRef }}"
                data-mode="{{ $mode }}"
                data-opt="{{ $opt ? '1' : '0' }}">
            Planning automatique
        </button>
        <span id="autoplanSpinner" class="autoplan-spinner" hidden>Calcul en cours…</span>
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
                                    $fakeForPeople = data_get($stop,'numint') ? data_get($stop,'numint') : ($agRef.'-0000-00000');
                                    $people = app(\App\Services\AccessInterventionService::class)
                                        ->listPeopleForNumInt($fakeForPeople)
                                        ->map(fn($p) => ['code'=>data_get($p,'CodeSal'),'name'=>data_get($p,'NomSal')])
                                        ->values();
                                @endphp

                                <div class="stop-mini-actions">
                                    <button type="button"
                                            class="btn-mini btn-replan"
                                            data-endpoint="{{ route('tournee.replanifier', ['numInt' => data_get($stop,'numint')]) }}"
                                            data-numint="{{ data_get($stop,'numint') }}"
                                            data-date="{{ $date }}"
                                            data-time="{{ $heureAff !== '—' ? $heureAff : '' }}"
                                            data-tech="{{ data_get($tour,'tech','') }}"
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

<div id="autoplanRibbon" class="autoplan-ribbon" hidden>Proposition de planning (aperçu)</div>
<div id="autoplanFooter" class="autoplan-footer" hidden>
    <div class="meta">
        <span id="apStats"></span>
    </div>
    <div class="actions">
        <button type="button" id="apCancel" class="btn-light">Annuler</button>
        <button type="button" id="apCommit" class="btn-primary">Valider ce planning</button>
    </div>
</div>

{{-- Leaflet --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

{{-- Bootstrap des données côté serveur (minime, nécessaire) --}}
<script>
    window.TP = {
        start: @json($start ?? null),
        tours: @json($techTours ?? []),
    };
</script>

{{-- Script page --}}
<script src="{{ asset('js/tourprep.js') }}" defer></script>
</body>
</html>
