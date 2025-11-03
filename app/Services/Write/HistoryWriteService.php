<?php

namespace App\Services\Write;

use Illuminate\Support\Facades\DB;

class HistoryWriteService
{
    public function log(string $numInt, ?string $type, ?array $meta, string $titre, string $texte, ?string $auteur): void
    {
        DB::table('t_suiviclient_histo')->insert([
            'NumInt'        => $numInt,
            'CreatedAt'     => now('Europe/Paris'),
            'CodeSalAuteur' => $auteur,
            'Titre'         => $titre,
            'Texte'         => $texte,
            'evt_type'      => $type,
            'evt_meta'      => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
