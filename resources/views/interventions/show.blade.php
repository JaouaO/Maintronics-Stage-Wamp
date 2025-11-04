@extends('layouts.base')
@section('title','Tickets / récap')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">
    <script src="{{ asset('js/interventions_show.js') }}" defer></script>

    <div class="app">
        <div class="header b-header">
            <h1>Interventions</h1>

            <div class="user-actions">
                {{-- Chip utilisateur (nom + bouton info) --}}
                <div  title="Informations utilisateur">
                    <span class="user-name">{{ $data->NomSal ?? session('codeSal') }}</span>
                    <button id="openUserModal"
                            class="btn-info-circle"
                            type="button"
                            aria-haspopup="dialog"
                            aria-controls="userInfoModal"
                            title="Voir les informations de la session">
                        i
                    </button>
                </div>

                <a class="btn-logout"
                   href="{{ route('deconnexion') }}"
                   title="Déconnexion">
                    <span class="ico">↪</span> Déconnexion
                </a>
            </div>
        </div>

        {{-- …votre code… --}}

        {{-- === Modale infos utilisateur === --}}
        <div class="modal" id="userInfoModal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle" hidden>
            <div class="modal-backdrop" data-close></div>
            <div class="modal-panel" role="document" tabindex="-1">
                <div class="modal-header">
                    <h2 id="userModalTitle">Informations de la session</h2>
                    <button class="icon-toggle" type="button" data-close aria-label="Fermer">✕</button>
                </div>

                <div class="modal-body">
                    <dl class="kv">
                        <dt>Nom</dt>
                        <dd>{{ $data->NomSal ?? '—' }}</dd>

                        <dt>Identifiant</dt>
                        <dd>{{ $data->CodeSal ?? session('codeSal') }}</dd>

                        <dt>Agence courante</dt>
                        <dd>{{ session('defaultAgence') ?: ($data->CodeAgSal ?? '—') }}</dd>

                        <dt>IP</dt>
                        <dd>{{ $data->IP ?? request()->ip() }}</dd>

                        <dt>Dernier accès</dt>
                        <dd>{{ $data->DateAcces ?? '—' }}</dd>
                    </dl>

                    @php $ags = (array) session('agences_autorisees', []); @endphp
                    @if(!empty($ags))
                        <div class="sub">
                            <strong>Agences autorisées :</strong>
                            <div class="tags-list" style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px">
                                @foreach($ags as $agc)
                                    <span class="tag t-OTHER">{{ $agc }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="modal-footer">
                    <a class="btn" href="{{ route('deconnexion') }}">Se déconnecter</a>
                    <button class="btn-light" type="button" data-close>Fermer</button>
                </div>
            </div>
        </div>


        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul style="margin:0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="b-subbar">
            {{-- FORMULAIRE UNIQUE : agence + recherche + filtres + per-page --}}
            <form id="filterForm" method="get" action="{{ route('interventions.show') }}" class="b-row">
                {{-- Scope piloté par les chips --}}
                <input type="hidden" name="scope" id="scope" value="{{ $scope ?? '' }}">

                {{-- Agences --}}
                @php
                    $ags = array_values((array)($agencesAutorisees ?? []));
                    $hasMany = count($ags) > 1;
                    $agSelected = $ag ?? null; // null => toutes (si $hasMany)
                    $lieu = $lieu ?? 'all';
                @endphp

                <div class="b-agence">
                    <label for="ag">Agence</label>
                    <select id="ag" name="ag" {{ (!$hasMany && !empty($ags)) ? 'disabled' : '' }}>
                        @if($hasMany)
                            <option value="_ALL" {{ $agSelected === null ? 'selected' : '' }}>Toutes les agences
                            </option>
                        @endif
                        @foreach($ags as $agc)
                            <option value="{{ $agc }}" {{ $agSelected === $agc ? 'selected' : '' }}>{{ $agc }}</option>
                        @endforeach
                    </select>
                    @if(!$hasMany && !empty($ags))
                        {{-- Les champs disabled ne soumettent pas : on duplique la valeur en hidden --}}
                        <input type="hidden" name="ag" value="{{ $ags[0] }}">
                    @endif
                </div>

                {{-- Lieu (Tous / Site / Laboratoire) --}}
                <div class="b-lieu">
                    <label for="lieu">Lieu</label>
                    <select id="lieu" name="lieu">
                        <option value="all" {{ $lieu==='all'  ? 'selected' : '' }}>Tous</option>
                        <option value="site" {{ $lieu==='site' ? 'selected' : '' }}>Site</option>
                        <option value="labo" {{ $lieu==='labo' ? 'selected' : '' }}>Laboratoire</option>
                    </select>
                </div>

                {{-- Recherche --}}
                <div class="b-search">
                    <label for="q" class="sr-only">Rechercher</label>
                    <input
                        type="search"
                        id="q"
                        name="q"
                        value="{{ old('q', $q) }}"
                        class="{{ $errors->has('q') ? 'is-invalid' : '' }}"
                        placeholder="Rechercher un n°, un client, un libellé “à faire”…">
                    @if(!empty(old('q', $q)))
                        <button type="button" class="b-clear" title="Effacer">✕</button>
                    @endif
                    @error('q')
                    <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Filtres (chips) --}}
                @php $scope = $scope ?? ''; @endphp
                @php
                    $isUrg = in_array($scope, ['urgent','both'], true);
                    $isMe  = in_array($scope, ['me','both'], true);
                @endphp
                <div class="b-filters">
                    <span class="b-label">Filtres :</span>
                    <button type="button" class="b-chip b-chip-urgent {{ $isUrg ? 'is-active' : '' }}"
                            data-role="urgent">
                        <span class="dot"></span> URGENT
                    </button>
                    <button type="button" class="b-chip b-chip-me {{ $isMe ? 'is-active' : '' }}" data-role="me">
                        <span class="dot"></span> VOUS
                    </button>
                </div>

                {{-- Lignes / page --}}
                <div class="b-perpage">
                    <label for="perpage">Lignes / page</label>
                    <select id="perpage" name="per_page">
                        @foreach([10,25,50,100] as $pp)
                            <option value="{{ $pp }}" {{ (int)$perPage===$pp ? 'selected':'' }}>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="b-apply">
                    <button class="btn" type="submit">Appliquer</button>
                </div>
            </form>

        </div>

        <div class="card">
            <div class="cardBody">
                <div class="tableArea">
                    <table class="table" id="intervTable">
                        <colgroup>
                            <col class="colw-id">
                            <col class="colw-code"> {{-- NOUVEAU --}}
                            <col class="colw-client">
                            <col class="colw-dt">
                            <col class="colw-todo">
                            <col class="colw-flags">
                            <col class="colw-act">
                        </colgroup>

                        <thead>
                        <tr>
                            <th class="col-id">N° Interv</th>
                            <th class="col-code">Code</th> {{-- NOUVEAU --}}
                            <th class="col-client">Client</th>
                            <th class="col-dt" data-sort="datetime">Date / Heure</th>
                            <th class="col-todo">À faire</th>
                            <th class="col-flags"></th>
                            <th class="col-actions">Actions</th>
                        </tr>
                        </thead>


                        <tbody id="rowsBody">
                        @forelse($rows as $row)
                            @php
                                $isUrgent = (int)($row->urgent ?? 0) === 1;
                                $isMe     = (int)($row->concerne ?? 0) === 1;
                                $isRdvVal = (int)($row->rdv_valide ?? 0) === 1;
                                $isSite   = (int)($row->is_site ?? 0) === 1;

                                $aff = [];
                                if (!empty($row->affectations) && (is_array($row->affectations) || $row->affectations instanceof \Traversable)) {
                                  foreach ($row->affectations as $a) {
                                    $code  = is_array($a) ? ($a['code'] ?? null) : ($a->code ?? null);
                                    $label = is_array($a) ? ($a['label'] ?? null) : ($a->label ?? null);
                                    if ($label) $aff[] = ['code'=>$code, 'label'=>$label];
                                  }
                                } else {
                                  $raw = trim((string)($row->a_faire_label ?? $row->a_faire ?? ''));
                                  if ($raw !== '') {
                                    $parts = preg_split('/\s*[|,;\/·]\s*/u', $raw);
                                    foreach ($parts as $p) { $p = trim($p); if ($p !== '') $aff[] = ['code'=>null, 'label'=>$p]; }
                                  }
                                }
                                $mapLabelToCode = [
                                  'RDV à fixer' => 'RDV_A_FIXER',
                                  'Client à recontacter' => 'CLIENT_A_RECONTACTER',
                                  'Commande pièces' => 'COMMANDE_PIECES',
                                  'Suivi escalade' => 'SUIVI_ESCALADE',
                                  'Confirmer contact client' => 'CONFIRMER_CONTACT',
                                  'Diagnostic à réaliser' => 'DIAGNOSTIC_A_REALISER',
                                  'Vérifier dispo pièce' => 'VERIFIER_DISPO_PIECE',
                                  'RDV confirmé' => 'RDV_CONFIRME',
                                ];
                                foreach ($aff as &$a) { if (empty($a['code'])) $a['code'] = $mapLabelToCode[$a['label']] ?? null; }
                                unset($a);

                                $affCount = count($aff);
                                $affFull  = $affCount >= 3 ? implode(' · ', array_map(fn($x)=>$x['label'],$aff)) : null;

                                $trClassBase = $isUrgent && $isMe ? 'row-urgent-me' : ($isUrgent ? 'row-urgent' : ($isMe ? 'row-me' : ''));
                                $tints = [];
                                if ($isUrgent) $tints[] = 'tint-urgent';
                                if ($isMe)     $tints[] = 'tint-me';
                                if ($isRdvVal) $tints[] = 'tint-rdv';
                                $trClass = trim($trClassBase.' '.implode(' ', $tints));

                                $rowId   = 'r_'.preg_replace('/[^A-Za-z0-9_-]/','',$row->num_int);
                                 // Code 'tech' si RDV validé, sinon 'salarié' (reaffecte_code).
    // On tolère l'absence de tech_code : fallback sur reaffecte_code.
    $codeRaw = $isRdvVal ? ($row->tech_code ?? $row->reaffecte_code ?? null)
                         : ($row->reaffecte_code ?? $row->tech_code ?? null);

    $code4 = null;
    if ($codeRaw) {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string)$codeRaw);
        $code4 = $clean !== '' ? strtoupper(mb_substr($clean, 0, 4)) : null;
    }
                            @endphp

                            @php
                                // Prépare l’affichage & le tri
                                $ts = null; $dtTxt = '—'; $tmTxt = '—'; $isToday = false;

                                if (!empty($row->date_prev) || !empty($row->heure_prev)) {
                                    try {
                                        $dtObj = \Carbon\Carbon::parse(trim(($row->date_prev ?? '') . ' ' . ($row->heure_prev ?? '00:00:00')));
                                        $ts    = $dtObj->timestamp;
                                        $dtTxt = $row->date_prev ? $dtObj->format('d/m/Y') : '—';
                                        $tmTxt = $row->heure_prev ? $dtObj->format('H:i')   : '—';
                                        $isToday = $dtObj->isToday();
                                    } catch (\Throwable $e) { /* ignore */ }
                                }
                            @endphp


                            <tr class="row {{ $trClass }}"
                                data-href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}"
                                data-row-id="{{ $rowId }}"
                                data-ts="{{ $ts ?? '' }}">
                                <td class="col-id">{{ $row->num_int }}</td>
                                <td class="col-code">
                                    <span class="code-chip">{{ $code4 ?? '—' }}</span>
                                </td>
                                <td class="col-client">{{ $row->client }}</td>
                                <td class="col-dt">
                                    <div class="dt-wrap">
                                        <span class="dt-date">{{ $dtTxt }}</span>
                                        <span class="dt-time">{{ $tmTxt }}</span>
                                    </div>
                                </td>
                                <td class="col-todo">
                                    @if($affCount >= 3)
                                        <span class="tag combo" aria-label="{{ $affFull }}"
                                              title="{{ $affFull }}">{{ $affFull }}</span>
                                    @else
                                        <span class="todo-tags">
                    @foreach($aff as $a)
                                                @php $cls = $a['code'] ? 't-'.$a['code'] : 't-OTHER'; @endphp
                                                <span class="tag {{ $cls }}">{{ $a['label'] }}</span>
                                            @endforeach
                </span>
                                    @endif
                                </td>
                                <td class="col-flags">
            <span class="flags">
                @if($isUrgent)
                    <span class="badge badge-urgent">URGENT</span>
                @endif
                @if($isMe)
                    <span class="badge badge-me">VOUS</span>
                @endif
                @if($isRdvVal)
                    <span class="badge badge-rdv">RDV validé</span>
                @endif   {{-- ★ badge --}}
            </span>
                                </td>
                                <td class="col-actions">
                                    <div class="actions">
                                        <a class="btn js-open"
                                           href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">Ouvrir</a>
                                        <button class="btn btn-light js-open-history" type="button"
                                                data-num-int="{{ $row->num_int }}"
                                                data-history-url="{{ route('interventions.history', $row->num_int) }}"
                                                title="Historique">Historique
                                        </button>
                                        <button class="icon-toggle js-row-toggle" type="button"
                                                aria-expanded="false" aria-controls="det-{{ $rowId }}"
                                                title="Plus d’infos" data-row-id="{{ $rowId }}">▾
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr class="row-detail" id="det-{{ $rowId }}" data-detail-for="{{ $rowId }}" hidden>
                                <td colspan="7" class="detail-cell">
                                    <div class="detail-wrap">
                                        <div><strong>N° :</strong> {{ $row->num_int }}</div>
                                        <div><strong>Client :</strong> {{ $row->client }}</div>
                                        <div><strong>Marque :</strong> {{ $row->marque ?? '—' }}</div>
                                        <div><strong>Ville / CP
                                                :</strong> {{ $row->ville ?? '—' }} @if(!empty($row->cp))
                                                ({{ $row->cp }})
                                            @endif</div>
                                        <div><strong>Tél. livraison :</strong> {{ $row->tel_liv_cli ?? '—' }}</div>
                                        <div class="full"><strong>Email livraison
                                                :</strong> {{ $row->email_liv_cli ?? '—' }}</div>
                                        <div class="full"><strong>Adresse livraison
                                                :</strong> {{ $row->ad_liv_cli ?? '—' }}</div>
                                        <div><strong>Type appareil :</strong> {{ $row->type_app ?? '—' }}</div>

                                        <div class="full"><strong>Commentaire :</strong> {{ $row->commentaire ?? '—' }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--mut);padding:16px">Aucune
                                    intervention
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="pager" class="pager">
                    {{ $rows->onEachSide(1)->appends([
    'per_page' => $perPage,
    'q'        => $q,
    'scope'    => $scope,
    'ag'       => $ag ?? ($hasMany ? '_ALL' : ($ags[0] ?? null)),
    'lieu'     => $lieu,
  ])->links('pagination.clean') }}

                </div>
            </div>
        </div>

        <div class="footer">
            <div class="meta">Priorité serveur : (URGENT & VOUS) → URGENT → VOUS → Autre, puis date/heure.</div>
            <div class="ft-actions">
                <a class="btn" href="{{ route('interventions.create') }}">➕ Nouvelle intervention</a>
                <a class="btn" href="{{ url()->previous() }}">Retour</a>
                <a class="btn" href="{{ route('interventions.show', [
    'per_page' => $perPage,
    'q'        => $q,
    'scope'    => $scope,
    'ag'       => $ag ?? ($hasMany ? '_ALL' : ($ags[0] ?? null)),
    'lieu'     => $lieu,
]) }}">Actualiser</a>

            </div>
        </div>
    </div>
@endsection
