<div class="box">
    <div class="head">
        <strong>Affectation du dossier</strong>
        <span id="srvDateTimeText" class="note">—</span>
    </div>

    <div class="body">
        <div class="affectationSticky">
            {{-- Affecter à --}}
            <div class="grid2">
                <label for="selAny">Affecter à</label>
                <div class="hstack-12">
                    <select
                        name="rea_sal"
                        id="selAny"
                        required
                        class="{{ $errors->has('rea_sal') ? 'is-invalid' : '' }}"
                        aria-invalid="{{ $errors->has('rea_sal') ? 'true' : 'false' }}">
                        <option value="">— Sélectionner —</option>
                        @if(($techniciens ?? collect())->count())
                            <optgroup label="Techniciens">
                                @foreach($techniciens as $t)
                                    <option value="{{ $t->CodeSal }}" {{ old('rea_sal') == $t->CodeSal ? 'selected' : '' }}>
                                        {{ $t->NomSal }} ({{ $t->CodeSal }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if(($salaries ?? collect())->count())
                            <optgroup label="Salariés">
                                @foreach($salaries as $s)
                                    <option value="{{ $s->CodeSal }}" {{ old('rea_sal') == $s->CodeSal ? 'selected' : '' }}>
                                        {{ $s->NomSal }} ({{ $s->CodeSal }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>

                    {{-- URGENT --}}
                    <label class="urgent-toggle" for="urgent">
                        <input type="hidden" name="urgent" value="0">
                        <input type="checkbox" id="urgent" name="urgent" value="1" {{ old('urgent') == '1' ? 'checked' : '' }}>
                        <span>Urgent</span>
                    </label>
                </div>
            </div>

            {{-- Date/Heure --}}
            <div class="gridRow gridRow--dt">
                <label for="dtPrev">Date</label>
                <input type="date" id="dtPrev" name="date_rdv" required
                       value="{{ old('date_rdv') }}"
                       class="{{ $errors->has('date_rdv') ? 'is-invalid' : '' }}"
                       aria-invalid="{{ $errors->has('date_rdv') ? 'true' : 'false' }}">
                <label for="tmPrev">Heure</label>
                <input type="time" id="tmPrev" name="heure_rdv" required
                       value="{{ old('heure_rdv') }}"
                       class="{{ $errors->has('heure_rdv') ? 'is-invalid' : '' }}"
                       aria-invalid="{{ $errors->has('heure_rdv') ? 'true' : 'false' }}">
            </div>

            {{-- Étapes AFFECTATION --}}
            <div class="table mt8 {{ $errors->has('affectation') || $errors->has('affectation.*') ? 'is-invalid-block' : '' }}">
                <table>
                    <thead>
                    <tr>
                        <th>Étapes de planification</th>
                        <th class="w-66">Statut</th>
                        <th>Étapes de planification</th>
                        <th class="w-66">Statut</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $pairs = array_chunk(($affectationItems ?? []), 2); @endphp
                    @forelse($pairs as $pair)
                        <tr>
                            <td>{{ $pair[0]['label'] ?? '' }}</td>
                            <td class="status">
                                @if(isset($pair[0]))
                                    <input type="hidden" name="affectation[{{ $pair[0]['code'] }}]" value="0">
                                    <input type="checkbox" name="affectation[{{ $pair[0]['code'] }}]" value="1"
                                        {{ old("affectation.{$pair[0]['code']}") === '1' ? 'checked' : '' }}>
                                @endif
                            </td>
                            <td>{{ $pair[1]['label'] ?? '' }}</td>
                            <td class="status">
                                @if(isset($pair[1]))
                                    <input type="hidden" name="affectation[{{ $pair[1]['code'] }}]" value="0">
                                    <input type="checkbox" name="affectation[{{ $pair[1]['code'] }}]" value="1"
                                        {{ old("affectation.{$pair[1]['code']}") === '1' ? 'checked' : '' }}>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="note">Aucun item d’affectation</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Boutons --}}
            <div class="flex-end-bar">
                <button id="btnPlanifierAppel" class="btn btn-plan-call btn-sm" type="button">Planifier un nouvel appel</button>
                <button id="btnPlanifierRdv" class="btn btn-plan-rdv btn-sm" type="button">Planifier un rendez-vous</button>
                <button id="btnValider" class="btn btn-validate" type="button">Valider le prochain rendez-vous</button>
            </div>
        </div>
    </div>
</div>
