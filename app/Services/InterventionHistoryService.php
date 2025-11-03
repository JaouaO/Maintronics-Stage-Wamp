<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InterventionHistoryService
{
    public function fetchHistory(string $numInt): \Illuminate\Support\Collection
    {
        // Ta table a une seule colonne de date : CreatedAt
        $dtExpr = "h.CreatedAt";

        $rows = DB::table('t_suiviclient_histo as h')
            ->select([
                'h.id',
                'h.NumInt',
                'h.Titre',
                'h.Texte',
                'h.CodeSalAuteur',
                'h.evt_type',
                'h.evt_meta',
                DB::raw("$dtExpr as dt"),
            ])
            ->where('h.NumInt', '=', $numInt)
            ->orderByRaw("$dtExpr DESC")
            ->limit(200)
            ->get();

        return $rows->map(function ($r) {
            // Normalisation evt_meta (JSON → array)
            $meta = [];
            if (!empty($r->evt_meta)) {
                try {
                    $meta = is_array($r->evt_meta) ? $r->evt_meta
                        : (json_decode((string)$r->evt_meta, true) ?: []);
                } catch (\Throwable $e) { $meta = []; }
            }
            $r->meta = $meta;

            // Résumé = 1ère ligne du texte
            $raw   = (string)($r->Texte ?? '');
            $first = trim(preg_split('/\R/u', $raw, 2)[0] ?? '');
            $first = rtrim($first, " \t—–-:;.,");
            $r->resume_short = mb_strimwidth($first !== '' ? $first : '—', 0, 120, '…', 'UTF-8');

            // Label d’événement (si typé)
            $r->evt_label = $this->buildEvtLabel($r->evt_type, $meta, (string)$r->dt);

            return $r;
        });
    }

    private function buildEvtLabel(?string $type, array $meta, ?string $fallbackDt): ?string
    {
        if (!$type) return null;

        $map = [
            'CALL_PLANNED'       => 'Appel planifié',
            'RDV_TEMP_INSERTED'  => 'RDV temporaire (créé)',
            'RDV_TEMP_UPDATED'   => 'RDV temporaire (mis à jour)',
            'RDV_FIXED'          => 'RDV validé',
        ];
        $label = $map[$type] ?? null;
        if (!$label) return null;

        $d = $meta['date'] ?? $meta['d'] ?? null;
        $h = $meta['heure'] ?? $meta['h'] ?? null;
        $t = $meta['tech']  ?? $meta['t'] ?? null;

        // Fallback sur CreatedAt si date/heure absentes dans meta
        if (!$d && $fallbackDt && preg_match('/^\d{4}-\d{2}-\d{2}/', $fallbackDt)) {
            $d = substr($fallbackDt, 0, 10);
            $h = $h ?: substr($fallbackDt, 11, 5);
        }

        $parts = [];
        if ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            [$y,$m,$dd] = explode('-', $d);
            $parts[] = $dd.'/'.$m.'/'.$y;
        }
        if ($h) $parts[] = $h;
        if ($t) $parts[] = $t;

        if ($parts) $label .= ' — '.implode(' · ', $parts);
        return $label;
    }
}
