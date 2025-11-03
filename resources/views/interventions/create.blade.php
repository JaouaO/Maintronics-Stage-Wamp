@extends('layouts.base')
@section('title','Nouvelle intervention')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/interventions.css') }}">
    <link rel="stylesheet" href="{{ asset('css/interventions_create.css') }}">
    <meta name="suggest-endpoint" content="{{ route('interventions.suggest') }}">

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul style="margin:0;padding-left:18px">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div id="CreateIntervPage" class="app new-interv">
        <div class="header b-header">
            <h1>Nouvelle intervention</h1>
        </div>

        <div class="card">
            <div class="cardBody">
                <form method="post" action="{{ route('interventions.store') }}" id="createForm" class="b-form">
                    @csrf

                    {{-- Agence + NumInt (auto) --}}
                    <div class="grid-agence-num">
                        <div class="b-field">
                            <label for="Agence">Agence</label>
                            <select id="Agence" name="Agence" required>
                                @foreach($agences as $ag)
                                    <option value="{{ $ag }}" {{ $ag === $agence ? 'selected' : '' }}>{{ $ag }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="b-field">
                            <label for="NumInt">Numéro d’intervention <span class="badge-auto">auto</span></label>
                            <input id="NumInt" name="NumInt" type="text" value="{{ $suggest }}" readonly>
                            @error('NumInt')<div class="err">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    {{-- Infos --}}
                    <div class="grid-infos mt-8">
                        <div class="b-field">
                            <label for="Marque">Marque</label>
                            <input id="Marque" name="Marque" type="text" value="{{ old('Marque', $defaults['Marque']) }}">
                        </div>
                        <div class="b-field">
                            <label for="VilleLivCli">Ville</label>
                            <input id="VilleLivCli" name="VilleLivCli" type="text" value="{{ old('VilleLivCli', $defaults['VilleLivCli']) }}">
                        </div>
                        <div class="b-field">
                            <label for="CPLivCli">CP <span class="hint">(optionnel)</span></label>
                            <input id="CPLivCli" name="CPLivCli" type="text" value="{{ old('CPLivCli', $defaults['CPLivCli']) }}">
                        </div>
                    </div>

                    {{-- RDV (optionnel) --}}
                    <div class="grid-rdv mt-8">
                        <div class="b-field">
                            <label for="DateIntPrevu">Date prévue</label>
                            <input id="DateIntPrevu" name="DateIntPrevu" type="date" value="{{ old('DateIntPrevu', $defaults['DateIntPrevu']) }}">
                        </div>
                        <div class="b-field">
                            <label for="HeureIntPrevu">Heure prévue</label>
                            <input id="HeureIntPrevu" name="HeureIntPrevu" type="time" value="{{ old('HeureIntPrevu', $defaults['HeureIntPrevu']) }}">
                        </div>
                        <div class="b-field">
                            <label for="Commentaire">Commentaire</label>
                            <input id="Commentaire" name="Commentaire" type="text" value="{{ old('Commentaire', $defaults['Commentaire']) }}">
                        </div>
                    </div>

                    {{-- Options --}}
                    <div class="options-row">
                        <label class="urgent-toggle" for="Urgent">
                            <input type="hidden" name="Urgent" value="0">
                            <input type="checkbox" id="Urgent" name="Urgent" value="1" {{ old('Urgent', $defaults['Urgent']) ? 'checked' : '' }}>
                            <span>Urgent</span>
                        </label>
                    </div>

                    {{-- Footer --}}
                    <div class="footer">
                        <div class="ft-actions">
                            <a class="btn-light no-underline" href="{{ route('interventions.show') }}">↩︎ Annuler</a>
                            <button class="btn-primary" type="submit">Créer l’intervention</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Script séparé --}}
    <script src="{{ asset('js/interventions_create.js') }}" defer></script>
@endsection
