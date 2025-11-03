<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Historique — {{ $numInt }}</title></head>
<body>
<h3>Historique du dossier {{ $numInt }}</h3>
<ul>
    @forelse($suivis as $s)
        <li>{{ $s->dt ?? '—' }} — {{ \Illuminate\Support\Str::limit($s->Texte ?? '', 120, '…') }}</li>
    @empty
        <li>Aucun suivi</li>
    @endforelse
</ul>
</body>
</html>
