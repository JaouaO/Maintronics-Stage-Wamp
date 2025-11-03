<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NoteService
{
    /**
     * Retourne la note interne (BLOB → UTF8) d'une intervention.
     */
    public function getInternalNote(string $numInt): string
    {
        $row = DB::table('t_intervention')
            ->selectRaw('CAST(CommentInterne AS CHAR CHARACTER SET utf8mb4) AS CommentInterneTxt')
            ->where('NumInt', $numInt)
            ->first();

        return $row->CommentInterneTxt ?? '';
    }

    /**
     * Met à jour la note interne (protégé contre l'injection via bindings).
     */
    public function updateInternalNote(string $numInt, ?string $note): int
    {
        return DB::table('t_intervention')
            ->where('NumInt', $numInt)
            ->update(['CommentInterne' => $note]);
    }
}
