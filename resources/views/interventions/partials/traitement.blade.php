<div class="box">
    <div class="head">
        <strong>Traitement du dossier — {{ $interv->NumInt }}</strong>
        <span class="note">{{ $data->NomSal ?? ($data->CodeSal ?? '—') }}</span>
    </div>

    <div class="body">
        {{-- Objet --}}
        <div class="grid2">
            <label>Objet</label>
            <div class="ro">{{ $objetTrait ?: '—' }}</div>
        </div>

        {{-- Contact réel + bouton infos client (depuis t_intervention) --}}
        <div class="grid2">
            <label for="contactReel">Contact réel</label>

            <div class="with-info">
                <input type="text" id="contactReel" name="contact_reel"
                       maxlength="255"
                       value="{{ old('contact_reel', $contactReel) }}"
                       class="{{ $errors->has('contact_reel') ? 'is-invalid' : '' }}"
                       aria-invalid="{{ $errors->has('contact_reel') ? 'true' : 'false' }}">

                <button type="button"
                        class="btn-info-circle info-btn"
                        aria-haspopup="dialog" aria-controls="infoModal"
                        title="Informations client"
                        data-type="client"
                        data-nom="{{ $interv->NomLivCli ?? '' }}"
                        data-tel="{{ $interv->TelLivCli ?? '' }}"
                        data-email="{{ $interv->EmailLivCli ?? '' }}"
                        data-adr="{{ $interv->AdLivCli ?? '' }}"
                        data-ville="{{ $interv->VilleLivCli ?? '' }}"
                        data-cp="{{ $interv->CPLivCli ?? '' }}"
                        data-typeapp="{{ $interv->TypeApp ?? '' }}"
                        data-marque="{{ $interv->Marque ?? '' }}">
                    i
                </button>
            </div>
        </div>
        {{-- Bouton historique --}}
        <button id="openHistory"
                class="btn btn-history btn-block"
                type="button"
                data-num-int="{{ $interv->NumInt }}">
            Ouvrir l’historique
        </button>

        {{-- Checklist TRAITEMENT --}}
        <div class="table mt6 {{ $errors->has('traitement') || $errors->has('traitement.*') ? 'is-invalid-block' : '' }}">
            <table>
                <tbody>
                @php $traits = $traitementItems ?? []; @endphp
                @forelse($traits as $trait)
                    <tr>
                        <td>{{ $trait['label'] }}</td>
                        <td class="status">
                            <input type="hidden" name="traitement[{{ $trait['code'] }}]" value="0">
                            <input type="checkbox"
                                   name="traitement[{{ $trait['code'] }}]"
                                   value="1"
                                {{ old("traitement.{$trait['code']}") === '1' ? 'checked' : '' }}>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="note">Aucun item de traitement</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
