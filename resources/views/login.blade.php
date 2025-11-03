@extends('layouts.base')

@section('title', 'Authentification')

@section('content')

    <h1>Authentification</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('authentification') }}">
        @csrf
        <label>Code salarié :</label>
        <input type="text" name="codeSal" required>
        <button type="submit">Se connecter</button>
    </form>

@endsection
