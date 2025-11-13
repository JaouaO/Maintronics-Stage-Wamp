{{-- resources/views/interventions/history_popup.blade.php --}}
@php
    // On s'assure que $numInt existe (string)
    $numInt = isset($numInt) ? (string)$numInt : '—';
@endphp
    <!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Historique {{ $numInt }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    {{-- On réutilise la même CSS que l’écran d’édition --}}
    <link rel="stylesheet" href="{{ asset('css/intervention_edit.css') }}">
</head>
<body>
<div class="box m-12">
    <div class="body">
        <div class="hist-wrap">
            <h2 class="hist-title">Historique du dossier {{ $numInt }}</h2>

            <table class="hist-table table">
                <thead>
                <tr>
                    <th class="w-150">Date</th>
                    <th>Résumé</th>
                    <th class="w-200">Rendez-vous / Appel</th>
                    <th class="w-40"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($suivis as $suivi)
                    @php
                        $dateTxt = '—';
                        $raw = (string)($suivi->Texte ?? '');
                        $resumeLine = trim(preg_split('/\R/', $raw, 2)[0] ?? '');
                        $resumeClean = rtrim($resumeLine, " \t—–-:;.,");
                        $resumeShort = \Illuminate\Support\Str::limit($resumeClean !== '' ? $resumeClean : '—', 60, '…');
                        $objet = trim((string)($suivi->Titre ?? ''));
                        $auteur = trim((string)($suivi->CodeSalAuteur ?? ''));

                        $evtType = $suivi->evt_type ?? null;
                        $meta = [];
                        if (!empty($suivi->evt_meta)) {
                            try {
                                $meta = (is_array($suivi->evt_meta)
                                    ? $suivi->evt_meta
                                    : json_decode($suivi->evt_meta, true)) ?: [];
                            } catch (\Throwable $e) {
                                $meta = [];
                            }
                        }

                        $dateIso = $meta['date'] ?? $meta['d'] ?? null;
                        $heure   = $meta['heure'] ?? $meta['h'] ?? null;
                        $tech    = $meta['tech']  ?? $meta['t'] ?? null;
                        $urgent  = (int)(
                            (isset($meta['urg']) && $meta['urg'])
                            || (isset($meta['urgent']) && $meta['urgent'])
                        );

                        $traitementList  = $meta['tl'] ?? [];
                        $affectationList = $meta['al'] ?? [];

                        if ($dateIso && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateIso)) {
                            [$yy,$mm,$dd] = explode('-', $dateIso);
                            $dateTxt = "$dd/$mm/$yy";
                        }

                        $evtLabel = null; $evtClass = null;
                        switch ($evtType) {
                            case 'CALL_PLANNED':
                                $evtLabel = 'Appel planifié';              $evtClass = 'badge-call';  break;
                            case 'RDV_TEMP_INSERTED':
                                $evtLabel = 'RDV temporaire (créé)';       $evtClass = 'badge-temp';  break;
                            case 'RDV_TEMP_UPDATED':
                                $evtLabel = 'RDV temporaire (mis à jour)'; $evtClass = 'badge-temp';  break;
                            case 'RDV_FIXED':
                                $evtLabel = 'RDV validé';                  $evtClass = 'badge-valid'; break;
                        }
                        if ($evtLabel) {
                            $parts = [];
                            if ($dateTxt && $heure)      $parts[] = $dateTxt.' à '.$heure;
                            elseif ($dateTxt)            $parts[] = $dateTxt;
                            elseif ($heure)              $parts[] = $heure;
                            if ($tech) $parts[] = $tech;
                            if (!empty($parts)) $evtLabel .= ' — ' . implode(' · ', $parts);
                        }
                    @endphp

                    <tr class="row-main" data-row="main">
                        <td class="cell-p6">{{ $dateTxt }}</td>
                        <td class="cell-p6" title="{{ $resumeClean }}">
                            @if($auteur !== '')
                                <strong>{{ $auteur }}</strong> —
                            @endif
                            @if($objet  !== '')
                                <em>{{ $objet }}</em> —
                            @endif
                            {{ $resumeShort }}
                        </td>

                        <td class="cell-p6">
                            @if($evtLabel)
                                <span class="badge {{ $evtClass }}">{{ $evtLabel }}</span>
                                @if($urgent)
                                    <span class="badge badge-urgent" aria-label="Dossier urgent">URGENT</span>
                                @endif
                            @else
                                <span class="note">—</span>
                            @endif
                        </td>

                        <td class="cell-p6 cell-center">
                            <button class="hist-toggle" type="button" aria-expanded="false" title="Afficher le détail">+</button>
                        </td>
                    </tr>

                    <tr class="row-details" data-row="details">
                        <td colspan="3" class="hist-details-cell">
                            @if($suivi->CodeSalAuteur)
                                <div><strong>Auteur :</strong> {{ $suivi->CodeSalAuteur }}</div>
                            @endif
                            @if($suivi->Titre)
                                <div><strong>Titre :</strong> <em>{{ $suivi->Titre }}</em></div>
                            @endif

                            <div class="prewrap mt8">{{ (string)($suivi->Texte ?? '') }}</div>

                            @if(!empty($traitementList) || !empty($affectationList))
                                <hr class="sep">
                                <div class="grid-2">
                                    <div>
                                        <div class="section-title">Tâches effectuées</div>
                                        @if(!empty($traitementList))
                                            <div class="chips-wrap">
                                                @foreach($traitementList as $lbl)
                                                    <span class="chip chip-green">{{ $lbl }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="note">—</div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="section-title">Affectation</div>
                                        @if(!empty($affectationList))
                                            <div class="chips-wrap">
                                                @foreach($affectationList as $lbl)
                                                    <span class="chip chip-amber">{{ $lbl }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="note">—</div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="note cell-p8-10">Aucun suivi</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Toggle des lignes de détails
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.hist-toggle'); if(!btn) return;
        var trMain = btn.closest('tr.row-main');    if(!trMain) return;
        var trDetails = trMain.nextElementSibling;
        if(!trDetails || !trDetails.matches('.row-details')) return;

        var open = trDetails.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? '–' : '+';
    }, {passive:true});
</script>
</body>
</html>
