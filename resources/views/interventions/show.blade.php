@extends('layouts.base')
@section('title','Tickets / récap')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">
    <script src="{{ asset('js/interventions_show.js') }}" defer></script>

    <div class="app">
        <div class="header b-header">
            <h1>Interventions</h1>

            <a class="btn-logout"
               href="{{ route('deconnexion') }}"
               title="Déconnexion">
                <span class="ico">↪</span> Déconnexion
            </a>
        </div>


        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul style="margin:0;padding-left:18px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li> {{-- échappé par Blade --}}
                    @endforeach
                </ul>
            </div>
        @endif


        <div class="b-subbar">
            {{-- FORMULAIRE UNIQUE : recherche + filtres + per-page --}}
            <form id="filterForm" method="get" action="{{ route('interventions.show') }}" class="b-row">
                {{-- Scope piloté par les chips --}}
                <input type="hidden" name="scope" id="scope" value="{{ $scope ?? '' }}">

                {{-- Recherche --}}
                <div class="b-search">
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


                {{-- Filtres (chips cliquables) --}}
                @php $scope = $scope ?? ''; @endphp
                @php
                    $isUrg = in_array($scope, ['urgent','both'], true);
                    $isMe  = in_array($scope, ['me','both'], true);
                @endphp
                <div class="b-filters">
                    <span class="b-label">Filtres :</span>
                    <button type="button" class="b-chip b-chip-urgent {{ $isUrg ? 'is-active' : '' }}" data-role="urgent">
                        <span class="dot"></span> URGENT
                    </button>
                    <button type="button" class="b-chip b-chip-me {{ $isMe ? 'is-active' : '' }}" data-role="me">
                        <span class="dot"></span> VOUS
                    </button>
                </div>

                {{-- Lignes / page (pas d’autosubmit) --}}
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
                            <col class="colw-client">
                            <col class="colw-date">
                            <col class="colw-heure">
                            <col class="colw-todo">
                            <col class="colw-flags">
                            <col class="colw-act">
                        </colgroup>
                        <thead>
                        <tr>
                            <th class="col-id" data-sort="text">N° Interv</th>
                            <th class="col-client" data-sort="text">Client</th>
                            <th class="col-date" data-sort="date">Date</th>
                            <th class="col-heure" data-sort="time">Heure</th>
                            <th class="col-todo" data-sort="text">À faire</th>
                            <th class="col-flags"></th>
                            <th class="col-actions">Actions</th>
                        </tr>
                        </thead>
                        <tbody id="rowsBody">
                        @forelse($rows as $row)
                            @php
                                $isUrgent = (int)($row->urgent ?? 0) === 1;
                                $isMe     = (int)($row->concerne ?? 0) === 1;

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
                                $trClassTint = $isUrgent ? 'tint-urgent' : ($isMe ? 'tint-me' : '');
                                $trClass     = trim($trClassBase.' '.$trClassTint);

                                $rowId   = 'r_'.preg_replace('/[^A-Za-z0-9_-]/','',$row->num_int);
                            @endphp

                            <tr class="row {{ $trClass }}" data-href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}" data-row-id="{{ $rowId }}">
                                <td class="col-id">{{ $row->num_int }}</td>
                                <td class="col-client">{{ $row->client }}</td>
                                <td class="col-date">{{ $row->date_prev ? \Carbon\Carbon::parse($row->date_prev)->format('d/m/Y') : '—' }}</td>
                                <td class="col-heure">{{ $row->heure_prev ? \Carbon\Carbon::parse($row->heure_prev)->format('H:i') : '—' }}</td>
                                <td class="col-todo">
                                    @if($affCount >= 3)
                                        <span class="tag combo" aria-label="{{ $affFull }}" title="{{ $affFull }}">{{ $affFull }}</span>
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
                                        @if($isUrgent)<span class="badge badge-urgent">URGENT</span>@endif
                                        @if($isMe)<span class="badge badge-me">VOUS</span>@endif
                                    </span>
                                </td>
                                <td class="col-actions">
                                    <div class="actions">
                                        <a class="btn js-open" href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">Ouvrir</a>
                                        <button class="btn btn-light js-open-history" type="button"
                                                data-num-int="{{ $row->num_int }}"
                                                data-history-url="{{ route('interventions.history', $row->num_int) }}"
                                                title="Historique">Historique</button>
                                        <button class="icon-toggle js-row-toggle" type="button"
                                                aria-expanded="false" aria-controls="det-{{ $rowId }}"
                                                title="Plus d’infos" data-row-id="{{ $rowId }}">▾</button>
                                    </div>
                                </td>
                            </tr>

                            <tr class="row-detail" id="det-{{ $rowId }}" data-detail-for="{{ $rowId }}" hidden>
                                <td colspan="7" class="detail-cell">
                                    <div class="detail-wrap">
                                        <div><strong>N° :</strong> {{ $row->num_int }}</div>
                                        <div><strong>Client :</strong> {{ $row->client }}</div>
                                        <div><strong>Marque :</strong> {{ $row->marque ?? '—' }}</div>
                                        <div><strong>Ville / CP :</strong> {{ $row->ville ?? '—' }} @if(!empty($row->cp)) ({{ $row->cp }}) @endif</div>
                                        <div class="full"><strong>Commentaire :</strong> {{ $row->commentaire ?? '—' }}</div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align:center;color:var(--mut);padding:16px">Aucune intervention</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="pager" class="pager">
                    {{ $rows->onEachSide(1)->appends(['per_page'=>$perPage, 'q'=>$q, 'scope'=>$scope])->links('pagination.clean') }}
                </div>
            </div>
        </div>

        <div class="footer">
            <div class="meta">Priorité serveur : (URGENT & VOUS) → URGENT → VOUS → Autre, puis date/heure.</div>
            <div class="ft-actions">
                <a class="btn" href="{{ route('interventions.create') }}">➕ Nouvelle intervention</a>
                <a class="btn" href="{{ url()->previous() }}">Retour</a>
                <a class="btn" href="{{ route('interventions.show', ['per_page'=>$perPage, 'q'=>$q, 'scope'=>$scope]) }}">Actualiser</a>
            </div>
        </div>
    </div>
@endsection
