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
    {{-- On réutilise la même CSS si dispo --}}
    <link rel="stylesheet" href="{{ asset('css/intervention_edit.css') }}">
    <style>
        /* petit filet de sécurité si la CSS n’est pas chargée */
        table{width:100%;border-collapse:collapse;font:14px system-ui}
        th,td{padding:6px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        .row-details{display:none}
        .row-details.is-open{display:table-row}
        .hist-details-cell{background:#fafafa}
        .hist-title{margin:6px 0 12px}
        .cell-center{text-align:center}
        .prewrap{white-space:pre-wrap}
        .note{color:#666}
        .w-150{width:150px}.w-200{width:200px}.w-40{width:40px}
        .cell-p6{padding:6px}.cell-p8-10{padding:8px 10px}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .sep{border:none;border-top:1px solid #e5e7eb;margin:10px 0}
        .badge{display:inline-block;padding:2px 6px;border-radius:999px;font-size:11px;line-height:1.2;border:1px solid #d6d9df}
        .badge-temp{background:#fff5dc;border-color:#e6b34e;color:#7a5400}
        .badge-valid{background:#e9f8ef;border-color:#0d6b2d;color:#0d6b2d}
        .badge-call{background:#e9f2ff;border-color:#7aa7ff;color:#214c9c}
        .badge-urgent{background:#ffe9e9;border-color:#d93025;color:#a31212;font-weight:700;letter-spacing:.2px}
        .chip{display:inline-block;padding:3px 8px;border-radius:999px;font-size:12px;line-height:1.1;border:1px solid #d6d9df;white-space:nowrap}
        .chip-green{background:#e9f8ef;border-color:#0d6b2d;color:#0d6b2d}
        .chip-amber{background:#fff5dc;border-color:#e6b34e;color:#7a5400}
        .chips-wrap{display:flex;flex-wrap:wrap;gap:6px}
        .hist-toggle{width:24px;height:24px;border:1px solid #d6d9df;border-radius:4px;background:#fff;cursor:pointer}
    </style>
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
                            try { $meta = (is_array($suivi->evt_meta) ? $suivi->evt_meta : json_decode($suivi->evt_meta, true)) ?: []; }
                            catch (\Throwable $e) { $meta = []; }
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
                            case 'CALL_PLANNED':      $evtLabel='Appel planifié';              $evtClass='badge-call';  break;
                            case 'RDV_TEMP_INSERTED': $evtLabel='RDV temporaire (créé)';       $evtClass='badge-temp';  break;
                            case 'RDV_TEMP_UPDATED':  $evtLabel='RDV temporaire (mis à jour)'; $evtClass='badge-temp';  break;
                            case 'RDV_FIXED':         $evtLabel='RDV validé';                  $evtClass='badge-valid'; break;
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
