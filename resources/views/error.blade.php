@extends('layouts.base')

@section('title', 'Erreur')

@section('content')

<h1>Erreur</h1>
<p>{{ $message }}</p>
<a href="{{ route('authentification') }}" class="btn">Retour à la connexion</a>
@endsection
