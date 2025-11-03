<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class InterventionService
{
    /**
     * Liste paginée pour interventions.show — source: t_actions_etat
     * Champs attendus par la Blade : num_int, client, date_prev, heure_prev, date_deb, date_fin, statut, code_tech, todo
     */
    public function listPaginatedSimple($perPage = 25)
    {
        $perPage = (int) $perPage ?: 25;

        return DB::table('t_actions_etat as ae')
            ->selectRaw("
                ae.NumInt AS num_int,

                /* 'client' = contact_reel si dispo, sinon objet_traitement, sinon libellé par défaut */
                COALESCE(NULLIF(ae.contact_reel,''), NULLIF(ae.objet_traitement,''), '(client inconnu)') AS client,

                /* Date/heure prévues = TECH prioritaire, sinon RDV prev */
                DATE(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS date_prev,
                TIME(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS heure_prev,

                /* Non présents dans t_actions_etat -> NULL (la Blade affiche '—') */
                NULL AS date_deb,
                NULL AS date_fin,
                NULL AS statut,

                /* Tech courant */
                ae.tech_code AS code_tech,

                /* TODO simple : tech planifié > rdv prévu > diagnostic */
                CASE
                  WHEN ae.tech_rdv_at IS NOT NULL THEN 'CONFIRMER_RDV'
                  WHEN ae.rdv_prev_at IS NOT NULL THEN 'PLANIFIER_RDV'
                  ELSE 'DIAGNOSTIC'
                END AS todo
            ")
            ->orderByRaw("
                (COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) IS NULL) ASC,
                COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) ASC,
                ae.NumInt ASC
            ")
            ->paginate($perPage);
    }

    /**
     * Variante non paginée (si utilisée ailleurs) + counts + “prochain”.
     */
    public function list(int $limit = 300): array
    {
        $rows = DB::table('t_actions_etat as ae')
            ->selectRaw("
                ae.NumInt AS num_int,
                COALESCE(NULLIF(ae.contact_reel,''), NULLIF(ae.objet_traitement,''), '(client inconnu)') AS client,
                DATE(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS date_prev,
                TIME(COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)) AS heure_prev,
                NULL AS date_deb,
                NULL AS date_fin,
                NULL AS statut,
                ae.tech_code AS code_tech,
                CASE
                  WHEN ae.tech_rdv_at IS NOT NULL THEN 'CONFIRMER_RDV'
                  WHEN ae.rdv_prev_at IS NOT NULL THEN 'PLANIFIER_RDV'
                  ELSE 'DIAGNOSTIC'
                END AS todo
            ")
            ->orderByRaw("
                (COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) IS NULL) ASC,
                COALESCE(ae.tech_rdv_at, ae.rdv_prev_at) ASC,
                ae.NumInt ASC
            ")
            ->limit($limit)
            ->get();

        // Compteurs par TODO (Laravel 7)
        $counts = $rows->groupBy('todo')->map(function ($g) { return $g->count(); });

        // “Prochain” = plus proche date+heure valides
        [$nextNumInt, $nextIndex] = $this->computeNext($rows);

        return [
            'rows'       => $rows,
            'counts'     => $counts,
            'nextIndex'  => $nextIndex,
            'nextNumInt' => $nextNumInt,
            'total'      => $rows->count(),
        ];
    }

    private function computeNext(Collection $rows): array
    {
        $bestIdx = null;
        $bestTs  = null;

        foreach ($rows as $idx => $r) {
            if (empty($r->date_prev) || empty($r->heure_prev)) continue;
            try {
                $ts = Carbon::parse($r->date_prev.' '.$r->heure_prev);
            } catch (\Throwable $e) {
                continue;
            }
            if ($bestTs === null || $ts->lt($bestTs)) {
                $bestTs  = $ts;
                $bestIdx = $idx;
            }
        }

        $num = $bestIdx !== null ? ($rows[$bestIdx]->num_int ?? null) : null;
        return [$num, $bestIdx];
    }
}
