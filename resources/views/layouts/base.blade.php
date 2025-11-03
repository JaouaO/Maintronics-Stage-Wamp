<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mon Projet')</title>

    {{-- CSS principal éventuel (optionnel ici, laissé simple) --}}
    @stack('head')
</head>
<body>
<main>
    @yield('content')
</main>

{{-- Scripts poussés depuis les pages --}}
@stack('scripts')
</body>
</html>
