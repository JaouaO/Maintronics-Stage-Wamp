@extends('layouts.base')
@section('title','Connexion')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">

    <div class="auth-shell">
        <div class="auth-app auth-card">
            <div class="cardBody">
                <div class="title-row">
                    <h1>Connexion</h1>
                    {{-- pill supprimé --}}
                </div>

                @if (session('message'))
                    <div class="alert alert-danger">{{ session('message') }}</div>
                @endif

                <p class="lead">Entrez votre identifiant (CodeSal) pour ouvrir votre session.</p>

                <form method="post" action="{{ route('authentification.post') }}" autocomplete="off" class="form">
                    @csrf

                    <div class="form-grid">
                        <div>
                            <label for="codeSal">Identifiant</label>
                            <input
                                id="codeSal"
                                name="codeSal"
                                type="text"
                                inputmode="latin"
                                placeholder="ex. DEDA"
                                value="{{ old('codeSal') }}"
                                class="{{ $errors->has('codeSal') ? 'is-invalid' : '' }}"
                                autofocus
                                required>
                            @error('codeSal')
                            <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="actions">
                            <button class="btn btn-primary" type="submit">Se connecter</button>
                            {{-- bouton "Besoin d’aide" supprimé --}}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function(){
                const el = document.getElementById('codeSal');
                if(el && !el.value) el.focus();
            })();
        </script>
    @endpush
@endsection
