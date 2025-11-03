@extends('layouts.base')
@section('title','Tickets / récap')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">

    <div class="app">
        <div class="header">
            <h1>File des tickets</h1>
            <div class="toolbar">
                <form method="get" action="{{ route('interventions.show') }}">
                    <label for="perpage">Lignes / page :</label>
                    <select id="perpage" name="per_page" onchange="this.form.submit()">
                        @foreach([10,25,50,100] as $pp)
                            <option value="{{ $pp }}" {{ (int)$perPage===$pp ? 'selected':'' }}>{{ $pp }}</option>
                        @endforeach
                    </select>
                    <noscript>
                        <button type="submit">OK</button>
                    </noscript>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="cardHead">
                <strong>Tickets en attente</strong>
                <div class="right" style="display:flex;gap:8px;align-items:center;color:var(--mut);font-size:12px">
                    <span><span class="dot coral"
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ff7a86"></span> À rappeler</span>
                    <span><span class="dot blue"
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#4da3ff"></span> Confirmer</span>
                    <span><span class="dot amber"
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ffc857"></span> Planifier</span>
                    <span><span class="dot violet"
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#a78bfa"></span> Diagnostic</span>
                    <span><span class="dot teal"
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#26c6a2"></span> Pièces</span>
                    <span><span class="dot green"
                                style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#3fc08a"></span> Clôture</span>
                </div>
            </div>

            <div class="cardBody">
                {{-- Zone table qui scrolle --}}
                <div class="tableArea">
                    <table class="table" id="intervTable">
                        <colgroup>
                            <col style="width:140px">
                            <col style="width:260px">
                            <col style="width:110px">
                            <col style="width:90px">
                            <col style="width:180px">
                            <col>
                        </colgroup>

                        <thead>
                        <tr>
                            <th class="col-id" data-sort="text">N° Interv</th>
                            <th class="col-client" data-sort="text">Client</th>
                            <th class="col-date" data-sort="date">Date</th>
                            <th class="col-heure" data-sort="time">Heure</th>
                            <th class="col-todo" data-sort="text">À faire</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                        </thead>

                        <tbody id="rowsBody">
                        @forelse($rows as $row)
                            @php $cls = $todoTagClass[$row->todo] ?? 'neutral'; @endphp
                            <tr class="row" data-href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">
                                <td class="col-id">{{ $row->num_int }}</td>
                                <td class="col-client">{{ $row->client }}</td>
                                <td class="col-date">
                                    {{ $row->date_prev ? \Carbon\Carbon::parse($row->date_prev)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="col-heure">
                                    {{ $row->heure_prev ? \Carbon\Carbon::parse($row->heure_prev)->format('H:i') : '—' }}
                                </td>
                                <td class="col-todo"><span
                                        class="tag {{ $cls }}">{{ str_replace('_',' ', $row->todo) }}</span></td>
                                <td class="col-actions">
                                    <a class="btn" href="{{ route('interventions.edit', ['numInt' => $row->num_int]) }}">Ouvrir</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center;color:var(--mut);padding:16px">Aucune intervention</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>


                {{-- Pagination stylée --}}
                <div id="pager" class="pager">
                    {{ $rows->onEachSide(1)->appends(['per_page'=>$perPage])->links('pagination.clean') }}
                </div>
            </div>
        </div>

        <div class="footer" style="display:flex;justify-content:space-between;align-items:center">
            <div class="meta" style="color:var(--mut);font-size:12px">Astuce : tri par date puis heure côté serveur par
                défaut. Cliquez les en-têtes pour trier côté navigateur.
            </div>
            <div>
                <a class="btn" href="{{ url()->previous() }}">Retour</a>
                <a class="btn" href="{{ route('interventions.show', ['per_page'=>$perPage]) }}">Actualiser</a>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/interventions.js') }}" defer></script>
@endsection
