@extends('layouts.base')
@section('title', 'Erreur')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">

    <div class="error-shell">
        <div class="error-app error-card">
            <div class="cardBody">
                <div class="title-row">
                    <h1>Erreur</h1>
                    {{-- pill supprimé --}}
                </div>

                <div class="error-head">
                    <div class="error-ico" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24">
                            <path fill="currentColor" d="M12 17q.425 0 .713-.288T13 16q0-.425-.288-.713T12 15q-.425 0-.713.288T11 16q0 .425.288.713T12 17m-1-4h2V7h-2zM12 22q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12t.788-3.9t2.137-3.175T8.1 2.788T12 2t3.9.788t3.175 2.137T21.212 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.137T12 22"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight:600">Une erreur est survenue</div>
                        <div style="color:var(--mut);font-size:12.5px">Merci de réessayer ou de revenir à l’accueil.</div>
                    </div>
                </div>

                <div class="alert alert-danger" role="alert">
                    {{ $message ?? 'Une erreur inattendue a été rencontrée.' }}
                </div>

                <div class="actions">
                    <a class="btn btn-primary" href="{{ route('authentification') }}">Retour à la connexion</a>
                    @if(session()->has('id'))
                        <a class="btn" href="{{ route('interventions.show', ['id'=>session('id')]) }}">Aller au récap</a>
                    @endif
                    <button class="btn btn-light" type="button" onclick="history.back()">← Précédent</button>
                </div>
            </div>
        </div>
    </div>
@endsection
