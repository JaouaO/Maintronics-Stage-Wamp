@extends('layouts.base')
@section('title','Tickets / récap')

@section('content')
    {{-- CSS / JS spécifiques à la page "Interventions" --}}
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">
    <script src="{{ asset('js/interventions_show.js') }}" defer></script>

    <div class="app">
        {{-- En-tête + chip utilisateur + déconnexion --}}
        <div class="header b-header">
            <h1>Interventions</h1>

            <div class="user-actions">
                {{-- Chip utilisateur (nom + bouton infos session) --}}
                <div title="Informations utilisateur">
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

        {{-- === Modale infos utilisateur (session) === --}}
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
                            <div class="tags-list">
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

        {{-- Affichage global des erreurs de validation (haut de page) --}}
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="alert-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ===== SUBBAR : filtres + agence + lieu + recherche + lignes/page ===== --}}
        <div class="b-subbar">
            {{-- Formulaire unique de filtrage / pagination --}}
            <form id="filterForm" method="get" action="{{ route('interventions.show') }}" class="b-row">
                {{-- Scope piloté par les chips (urgent / me / both / vide) --}}
                <input type="hidden" name="scope" id="scope" value="{{ $scope ?? '' }}">

                {{-- Agences accessibles pour cet utilisateur --}}
                @php
                    $ags     = array_values((array)($agencesAutorisees ?? []));
                    $hasMany = count($ags) > 1;

                    // On fait confiance au contrôleur : $ag == null => "Toutes"
                    $agSelected = (is_string($ag ?? null) && in_array($ag, $ags, true)) ? $ag : null;

                    // Valeur utilisée pour la pagination / lien "Actualiser"
                    // - null + $hasMany => _ALL (toutes agences)
                    // - sinon agence unique ou sélectionnée
                    $agOut = $agSelected ?? ($hasMany ? '_ALL' : ($ags[0] ?? null));
                @endphp

                {{-- Select agence (désactivé si une seule agence) --}}
                <div class="b-agence">
                    <label for="ag">Agence</label>
                    <select id="ag" name="ag" {{ (!$hasMany && !empty($ags)) ? 'disabled' : '' }}>
                        @if($hasMany)
                            <option value="_ALL" {{ is_null($agSelected) ? 'selected' : '' }}>
                                Toutes les agences
                            </option>
                        @endif
                        @foreach($ags as $agc)
                            <option value="{{ $agc }}" {{ $agSelected === $agc ? 'selected' : '' }}>
                                {{ $agc }}
                            </option>
                        @endforeach
                    </select>

                    @if(!$hasMany && !empty($ags))
                        {{-- Les champs disabled ne sont pas soumis : on duplique en hidden --}}
                        <input type="hidden" name="ag" value="{{ $ags[0] }}">
                    @endif
                </div>

                {{-- Filtre lieu : tous / site / labo --}}
                <div class="b-lieu">
                    <label for="lieu">Lieu</label>
                    <select id="lieu" name="lieu">
                        <option value="all"  {{ $lieu === 'all'  ? 'selected' : '' }}>Tous</option>
                        <option value="site" {{ $lieu === 'site' ? 'selected' : '' }}>Site</option>
                        <option value="labo" {{ $lieu === 'labo' ? 'selected' : '' }}>Laboratoire</option>
                    </select>
                </div>

                {{-- Recherche texte full-table (numéro, client, libellé "à faire") --}}
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
                        {{-- Bouton pour effacer rapidement la recherche --}}
                        <button type="button" class="b-clear" title="Effacer">✕</button>
                    @endif
                    @error('q')
                    <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Chips de filtre (urgent / vous) --}}
                @php $scope = $scope ?? ''; @endphp
                @php
                    $isUrg = in_array($scope, ['urgent','both'], true);
                    $isMe  = in_array($scope, ['me','both'], true);
                @endphp
                <div class="b-filters">
                    <span class="b-label">Filtres :</span>
                    <button type="button"
                            class="b-chip b-chip-urgent {{ $isUrg ? 'is-active' : '' }}"
                            data-role="urgent">
                        <span class="dot"></span> URGENT
                    </button>
                    <button type="button"
                            class="b-chip b-chip-me {{ $isMe ? 'is-active' : '' }}"
                            data-role="me">
                        <span class="dot"></span> VOUS
                    </button>
                </div>

                {{-- Nombre de lignes par page --}}
                <div class="b-perpage">
                    <label for="perpage">Lignes / page</label>
                    <select id="perpage" name="per_page">
                        @foreach([10,25,50,100] as $pp)
                            <option value="{{ $pp }}" {{ (int) $perPage === $pp ? 'selected' : '' }}>
                                {{ $pp }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Bouton d’application des filtres --}}
                <div class="b-apply">
                    <button class="btn" type="submit">Appliquer</button>
                </div>
            </form>
        </div>

        {{-- ===== TABLEAU PRINCIPAL ===== --}}
        <div class="card">
            <div class="cardBody">
                <div class="tableArea">
                    <table class="table" id="intervTable">
                        {{-- Largeurs de colonnes gérées par colgroup + CSS --}}
                        <colgroup>
                            <col class="colw-id">
                            <col class="colw-code">
                            <col class="colw-client">
                            <col class="colw-dt">
                            <col class="colw-todo">
                            <col class="colw-flags">
                            <col class="colw-act">
                        </colgroup>

                        <thead>
                        <tr>
                            <th class="col-id">N° Interv</th>
                            <th class="col-code">Code</th>
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
                                // Drapeaux métier venant de la requête
                                $isUrgent = (int)($row->urgent ?? 0) === 1;
                                $isMe     = (int)($row->concerne ?? 0) === 1;
                                $isRdvVal = (int)($row->rdv_valide ?? 0) === 1;
                                $isSite   = (int)($row->is_site ?? 0) === 1;

                                // Normalisation de la liste "à faire"
                                $aff = [];
                                if (!empty($row->affectations)
                                    && (is_array($row->affectations) || $row->affectations instanceof \Traversable)
                                ) {
                                    foreach ($row->affectations as $a) {
                                        $code  = is_array($a) ? ($a['code'] ?? null) : ($a->code ?? null);
                                        $label = is_array($a) ? ($a['label'] ?? null) : ($a->label ?? null);
                                        if ($label) {
                                            $aff[] = ['code' => $code, 'label' => $label];
                                        }
                                    }
                                } else {
                                    $raw = trim((string)($row->a_faire_label ?? $row->a_faire ?? ''));
                                    if ($raw !== '') {
                                        $parts = preg_split('/\s*[|,;\/·]\s*/u', $raw);
                                        foreach ($parts as $p) {
                                            $p = trim($p);
                                            if ($p !== '') {
                                                $aff[] = ['code' => null, 'label' => $p];
                                            }
                                        }
                                    }
                                }

                                $mapLabelToCode = [
                                    'RDV à fixer'              => 'RDV_A_FIXER',
                                    'Client à recontacter'     => 'CLIENT_A_RECONTACTER',
                                    'Commande pièces'          => 'COMMANDE_PIECES',
                                    'Suivi escalade'           => 'SUIVI_ESCALADE',
                                    'Confirmer contact client' => 'CONFIRMER_CONTACT',
                                    'Diagnostic à réaliser'    => 'DIAGNOSTIC_A_REALISER',
                                    'Vérifier dispo pièce'     => 'VERIFIER_DISPO_PIECE',
                                    'RDV confirmé'             => 'RDV_CONFIRME',
                                ];

                                foreach ($aff as &$a) {
                                    if (empty($a['code'])) {
                                        $a['code'] = $mapLabelToCode[$a['label']] ?? null;
                                    }
                                }
                                unset($a);

                                $affCount = count($aff);
                                $affFull  = $affCount >= 3
                                    ? implode(' · ', array_map(fn($x) => $x['label'], $aff))
                                    : null;

                                $trClassBase = $isUrgent && $isMe ? 'row-urgent-me'
                                    : ($isUrgent ? 'row-urgent' : ($isMe ? 'row-me' : ''));

                                $tints = [];
                                if ($isUrgent) $tints[] = 'tint-urgent';
                                if ($isMe)     $tints[] = 'tint-me';
                                if ($isRdvVal) $tints[] = 'tint-rdv';

                                $trClass = trim($trClassBase.' '.implode(' ', $tints));

                                $rowId   = 'r_'.preg_replace('/[^A-Za-z0-9_-]/', '', $row->num_int);

                                $codeRaw = $isRdvVal
                                    ? ($row->tech_code ?? $row->reaffecte_code ?? null)
                                    : ($row->reaffecte_code ?? $row->tech_code ?? null);

                                $code4 = null;
                                if ($codeRaw) {
                                    $clean = preg_replace('/[^A-Za-z0-9]/', '', (string)$codeRaw);
                                    $code4 = $clean !== '' ? strtoupper(mb_substr($clean, 0, 4)) : null;
                                }
                            @endphp

                            @php
                                $ts = null;
                                $dtTxt = '—';
                                $tmTxt = '—';

                                if (!empty($row->date_prev) || !empty($row->heure_prev)) {
                                    try {
                                        $dtObj = \Carbon\Carbon::parse(
                                            trim(($row->date_prev ?? '') . ' ' . ($row->heure_prev ?? '00:00:00'))
                                        );
                                        $ts    = $dtObj->timestamp;
                                        $dtTxt = $row->date_prev ? $dtObj->format('d/m/Y') : '—';
                                        $tmTxt = $row->heure_prev ? $dtObj->format('H:i')   : '—';
                                    } catch (\Throwable $e) {
                                        //
                                    }
                                }
                            @endphp

                            {{-- Ligne principale cliquable --}}
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
                                        <span class="tag combo"
                                              aria-label="{{ $affFull }}"
                                              title="{{ $affFull }}">
                                            {{ $affFull }}
                                        </span>
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
                                        @endif
                                    </span>
                                </td>

                                <td class="col-actions">
                                    <div class="actions">
                                        <a class="btn js-open"
                                           href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">
                                            Ouvrir
                                        </a>

                                        <button class="btn btn-light js-open-history" type="button"
                                                data-num-int="{{ $row->num_int }}"
                                                data-history-url="{{ route('interventions.history', $row->num_int) }}"
                                                title="Historique">
                                            Historique
                                        </button>

                                        <button class="icon-toggle js-row-toggle" type="button"
                                                aria-expanded="false" aria-controls="det-{{ $rowId }}"
                                                title="Plus d’infos" data-row-id="{{ $rowId }}">
                                            ▾
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            {{-- Ligne de détails (accordéon) --}}
                            <tr class="row-detail" id="det-{{ $rowId }}" data-detail-for="{{ $rowId }}" hidden>
                                <td colspan="7" class="detail-cell">
                                    <div class="detail-wrap">
                                        <div><strong>N° :</strong> {{ $row->num_int }}</div>
                                        <div><strong>Client :</strong> {{ $row->client }}</div>
                                        <div><strong>Marque :</strong> {{ $row->marque ?? '—' }}</div>
                                        <div>
                                            <strong>Ville / CP :</strong>
                                            {{ $row->ville ?? '—' }}
                                            @if(!empty($row->cp))
                                                ({{ $row->cp }})
                                            @endif
                                        </div>
                                        <div><strong>Tél. livraison :</strong> {{ $row->tel_liv_cli ?? '—' }}</div>
                                        <div class="full">
                                            <strong>Email livraison :</strong> {{ $row->email_liv_cli ?? '—' }}
                                        </div>
                                        <div class="full">
                                            <strong>Adresse livraison :</strong> {{ $row->ad_liv_cli ?? '—' }}
                                        </div>
                                        <div><strong>Type appareil :</strong> {{ $row->type_app ?? '—' }}</div>

                                        <div class="full">
                                            <strong>Commentaire :</strong> {{ $row->commentaire ?? '—' }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="table-empty">
                                    Aucune intervention
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div id="pager" class="pager">
                    {{ $rows->onEachSide(1)->appends([
                        'per_page' => $perPage,
                        'q'        => $q,
                        'scope'    => $scope,
                        'ag'       => $agOut,
                        'lieu'     => $lieu,
                    ])->links('pagination.clean') }}
                </div>
            </div>
        </div>

        {{-- Footer d’aide + actions globales --}}
        <div class="footer">
            <div class="meta">
                Priorité serveur : (URGENT & VOUS) → URGENT → VOUS → Autre, puis date/heure.
            </div>
            <div class="ft-actions">
                <a class="btn" href="{{ route('interventions.create') }}">➕ Nouvelle intervention</a>
                <a class="btn" href="{{ url()->previous() }}">Retour</a>
                <a class="btn"
                   href="{{ route('interventions.show', [
                        'per_page' => $perPage,
                        'q'        => $q,
                        'scope'    => $scope,
                        'ag'       => $agOut,
                        'lieu'     => $lieu,
                   ]) }}">
                    Actualiser
                </a>
            </div>
        </div>
    </div>
@endsection
