<div class="box agendaBox" id="agendaBox">
    <div class="head">
        <strong>Agenda technicien</strong>
        <span class="note">vue mensuelle (Tous par défaut)</span>
    </div>
    <div class="body">
        {{-- Sélecteur de technicien --}}
        <div class="grid2">
            <label>Technicien(nes)</label>

            @php
                $ap = collect($agendaPeople ?? []);
                $byLevel = $ap->groupBy('access_level');
                $levels = ['interne' => 'Interne', 'direction' => 'Direction', 'externe' => 'Externe'];
                $currentTech = old('rea_sal'); // adaptez si vous avez une variable spécifique
            @endphp

            <select id="selModeTech">
                <option value="_ALL" selected>Toutes (filtrées)</option>

                @foreach($levels as $lvlKey => $lvlLabel)
                    @php
                        $grp = ($byLevel[$lvlKey] ?? collect())->sort(function($a,$b){
                            $ra = !empty($a->is_tech) ? 0 : 1;
                            $rb = !empty($b->is_tech) ? 0 : 1;
                            return ($ra <=> $rb) ?: strcasecmp((string)$a->NomSal, (string)$b->NomSal);
                        });
                    @endphp

                    @if($grp->count())
                        <optgroup label="{{ $lvlLabel }}">
                            @foreach($grp as $p)
                                <option value="{{ $p->CodeSal }}"
                                        {{ (string)$currentTech === (string)$p->CodeSal ? 'selected' : '' }}
                                        data-level="{{ $p->access_level }}"
                                        data-tech="{{ !empty($p->is_tech) ? 1 : 0 }}"
                                        data-hasrdv="{{ !empty($p->has_rdv) ? 1 : 0 }}"
                                        data-hasrdvag="{{ !empty($p->has_rdv_ag) ? 1 : 0 }}">
                                    {{ $p->NomSal }} ({{ $p->CodeSal }}) — {{ $p->CodeAgSal ?? '' }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                @endforeach
            </select>
        </div>



        <div id="calWrap">
            <div id="calHead">
                <button id="calPrev" class="btn" type="button">◀</button>
                <div id="calHeadMid">
                    <div id="calTitle"></div>
                    <button id="calToggle" class="btn" type="button" aria-expanded="true">▾ Mois</button>
                </div>
                <button id="calNext" class="btn" type="button">▶</button>
            </div>

            {{-- Grille du mois --}}
            <div id="calGrid" class="cal-grid"></div>

            {{-- Liste du jour --}}
            <div id="calList" class="cal-list is-hidden">
                <div id="calListHead">
                    <button id="dayPrev" class="btn" type="button" title="Jour précédent" aria-label="Jour précédent">◀</button>
                    <div id="calListTitle"></div>
                    <button id="dayNext" class="btn" type="button" title="Jour suivant" aria-label="Jour suivant">▶</button>
                </div>
                <div id="calListBody" class="table">
                    <table>
                        <thead>
                        <tr>
                            <th class="w-80">Heure</th>
                            <th class="w-80">Tech</th>
                            <th class="w-200">Contact</th>
                            <th>Label</th>
                            <th class="col-icon"></th>
                        </tr>
                        </thead>
                        <tbody id="calListRows"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- /calWrap -->
    </div>
</div>
