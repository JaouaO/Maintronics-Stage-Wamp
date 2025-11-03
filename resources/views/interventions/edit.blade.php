@extends('layouts.base')
@section('title', 'Intervention')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/intervention_edit.css') }}">

    @include('interventions.partials.errors')

    <form id="interventionForm" method="POST"
          onsubmit="return confirm('Confirmer la validation?');"
          action="{{ route('interventions.update', $interv->NumInt) }}">
        @csrf
        {{-- Champs cachés communs --}}
        <input type="hidden" name="code_sal_auteur" value="{{ $data->CodeSal ?? 'Utilisateur' }}">
        <input type="hidden" name="marque" value="{{ $interv->Marque ?? '' }}">
        <input type="hidden" name="objet_trait" value="{{ $objetTrait ?? '' }}">
        <input type="hidden" name="code_postal" value="{{ $interv->CPLivCli ?? '' }}">
        <input type="hidden" name="ville" value="{{ $interv->VilleLivCli ?? '' }}">
        <input type="hidden" name="action_type" id="actionType" value="">

        <div class="app">
            {{-- COLONNE CENTRE / GAUCHE --}}
            <section class="col center">
                @include('interventions.partials.traitement', [
                    'interv'           => $interv,
                    'data'             => $data,
                    'objetTrait'       => $objetTrait,
                    'contactReel'      => $contactReel,
                    'traitementItems'  => $traitementItems ?? [],
                    'suivis'           => $suivis,
                ])

                @include('interventions.partials.history_template', [
                    'interv' => $interv,
                    'suivis' => $suivis,
                ])

                @include('interventions.partials.commentaire')
            </section>

            {{-- COLONNE DROITE --}}
            <section class="col right">
                @include('interventions.partials.affectation', [
                    'techniciens'       => $techniciens,
                    'salaries'          => $salaries ?? collect(),
                    'affectationItems'  => $affectationItems ?? [],
                    'serverNow'         => $serverNow,
                ])

                @include('interventions.partials.agenda', [
                    'techniciens' => $techniciens,
                ])
            </section>
        </div>
    </form>

    @include('interventions.partials.modal')

    @include('interventions.partials.scripts', [
        'techniciens' => $techniciens,
        'serverNow'   => $serverNow,
    ])
@endsection
