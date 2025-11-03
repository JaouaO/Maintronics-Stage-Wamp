<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InterventionService
{
    /**
     * N'affiche que les interventions dont le NumInt commence par une agence autorisée.
     * - client        = contact_reel (fallback "(contact inconnu)")
     * - a_faire       = objet_traitement (fallback "À préciser")
     * - date/heure    = COALESCE(tech_rdv_at, rdv_prev_at)
     * - urgent        = t_actions_etat.urgent
     * - concerne      = reaffecte_code==$codeSal OR tech_code==$codeSal
     * - tri           = concerne DESC, urgent DESC, date/heure ASC, NumInt ASC
     */
// App\Services\InterventionService::listPaginatedSimple(...)

    // App\Services\InterventionService

    // App\Services\InterventionService


    public function listPaginatedSimple(
        int     $perPage = 25,
        array   $agencesAutorisees = [],
        ?string $codeSal = null,
        ?string $q = null,
        ?string $scope = null
    ): LengthAwarePaginator
    {
        // --- garde accès : agences
        $agences = array_values(array_unique(
            array_filter(array_map(function ($ag) {
                // normalise et whiteliste (ex : M34M, DOAG…)
                $ag = strtoupper(trim((string)$ag));
                return preg_match('/^[A-Z0-9_-]{2,8}$/', $ag) ? $ag : null;
            }, (array)$agencesAutorisees))
        ));
        if (empty($agences)) {
            return new LengthAwarePaginator(collect(), 0, $perPage);
        }

        // --- garde scope : si 'me' / 'both' mais codeSal absent → vide
        $scopeNorm = $scope ? strtolower($scope) : null;
        if (in_array($scopeNorm, ['me','both'], true) && empty($codeSal)) {
            return new LengthAwarePaginator(collect(), 0, $perPage);
        }

        // --- sanitisation "q" défensive (la FormRequest l’a déjà fait, mais on double ici)
        $q = is_string($q) ? trim($q) : null;
        if ($q !== null) {
            // enlève contrôles, borne à 120, retire < >
            $q = preg_replace('/[\x00-\x1F\x7F]/u', '', $q);
            $q = str_replace(['<','>'], '', $q);
            $q = mb_substr($q, 0, 120);
            if ($q === '') $q = null;
        }

        $query = DB::table('t_actions_etat as ae')
            ->leftJoin('t_actions_vocabulaire as v', function ($join) {
                $join->on('v.code', '=', 'ae.objet_traitement')
                    ->where('v.group_code', '=', 'AFFECTATION');
            })
            ->leftJoin('t_intervention as ti', 'ti.NumInt', '=', 'ae.NumInt')
            ->selectRaw("
            ae.NumInt AS num_int,
            COALESCE(NULLIF(ae.contact_reel,''), '(contact inconnu)') AS client,
            DATE(ae.rdv_prev_at) AS date_prev,
            TIME(ae.rdv_prev_at) AS heure_prev,

            ae.reaffecte_code,
            ae.urgent,

            CASE WHEN ae.reaffecte_code = ? THEN 1 ELSE 0 END AS concerne,

            v.code  AS a_faire_code,
            COALESCE(NULLIF(v.label,''), COALESCE(NULLIF(ae.objet_traitement,''), 'À préciser')) AS a_faire_label,

            ti.Marque AS marque,
            ti.VilleLivCli AS ville,
            ti.CPLivCli   AS cp,
            ae.commentaire AS commentaire,

            CASE
              WHEN ae.urgent=1 AND ae.reaffecte_code = ? THEN 0
              WHEN ae.urgent=1 THEN 1
              WHEN ae.reaffecte_code = ? THEN 2
              ELSE 3
            END AS tier
        ", [$codeSal, $codeSal, $codeSal]);

        // --- Filtre agences (par préfixe de NumInt)
        $query
            ->whereNotNull('ae.rdv_prev_at')
            ->whereRaw("NULLIF(TRIM(ae.reaffecte_code), '') IS NOT NULL")
            ->where(function ($qW) use ($agences) {
            foreach ($agences as $i => $ag) {
                $pattern = $ag.'%'; // bindé
                if ($i === 0) {
                    $qW->where('ae.NumInt', 'like', $pattern);
                } else {
                    $qW->orWhere('ae.NumInt', 'like', $pattern);
                }
            }
        });

        // --- Scope
        if ($scopeNorm === 'urgent') {
            $query->where('ae.urgent', 1);
        } elseif ($scopeNorm === 'me') {
            $query->where('ae.reaffecte_code', $codeSal);
        } elseif ($scopeNorm === 'both') {
            $query->where('ae.urgent', 1)
                ->where('ae.reaffecte_code', $codeSal);
        }

        // --- Recherche q (LIKE échappé)
        if ($q !== null) {
            // échappe \ puis % et _
            $safe = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q);
            $like = "%{$safe}%";
            $query->where(function ($w) use ($like) {
                $w->where('ae.NumInt', 'like', $like)
                    ->orWhere('ae.contact_reel', 'like', $like)
                    ->orWhere('v.label', 'like', $like)
                    ->orWhere('ae.objet_traitement', 'like', $like);
            });
        }

        // --- Tri : priorité (tier) puis rdv, puis NumInt
        $query->orderBy('tier', 'asc')
            ->orderByRaw("ae.rdv_prev_at IS NULL ASC")
            ->orderBy('ae.rdv_prev_at', 'asc')
            ->orderBy('ae.NumInt', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Variante non paginée (même logique de filtre/tri) si besoin ailleurs.
     */
    public function list(int $limit = 300, array $agencesAutorisees = [], ?string $codeSal = null): array
    {
        if (empty($agencesAutorisees)) {
            return [
                'rows' => collect(),
                'counts' => collect(),
                'nextIndex' => null,
                'nextNumInt' => null,
                'total' => 0,
            ];
        }

        $rows = DB::table('t_actions_etat as ae')
            ->selectRaw("
    ae.NumInt AS num_int,
    COALESCE(NULLIF(ae.contact_reel,''), '(contact inconnu)') AS client,
    COALESCE(NULLIF(ae.objet_traitement,''), 'À préciser') AS a_faire,
    DATE(ae.rdv_prev_at) AS date_prev,
    TIME(ae.rdv_prev_at) AS heure_prev,
    ae.reaffecte_code,
    ae.urgent AS urgent,
    CASE WHEN ae.reaffecte_code = ? THEN 1 ELSE 0 END AS concerne
    ", [$codeSal, $codeSal])
            ->where(function ($q) use ($agencesAutorisees) {
                foreach ($agencesAutorisees as $i => $ag) {
                    if (!is_string($ag) || $ag === '') continue;
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}('ae.NumInt', 'like', $ag . '%');
                }
            })
            ->whereNotNull('ae.rdv_prev_at')
            ->orderByDesc('concerne')
            ->orderByDesc('urgent')
            ->orderByRaw("ae.rdv_prev_at IS NULL ASC")
            ->orderByRaw("ae.rdv_prev_at ASC")
            ->orderBy('ae.NumInt', 'ASC')
            ->limit($limit)
            ->get();

        $counts = $rows->groupBy('a_faire')->map->count();
        [$nextNumInt, $nextIndex] = $this->computeNext($rows);

        return [
            'rows' => $rows,
            'counts' => $counts,
            'nextIndex' => $nextIndex,
            'nextNumInt' => $nextNumInt,
            'total' => $rows->count(),
        ];
    }

    private function computeNext(Collection $rows): array
    {
        $bestIdx = null;
        $bestTs = null;

        foreach ($rows as $idx => $r) {
            if (empty($r->date_prev) || empty($r->heure_prev)) continue;
            try {
                $ts = Carbon::parse($r->date_prev . ' ' . $r->heure_prev);
            } catch (\Throwable $e) {
                continue;
            }
            if ($bestTs === null || $ts->lt($bestTs)) {
                $bestTs = $ts;
                $bestIdx = $idx;
            }
        }

        $num = $bestIdx !== null ? ($rows[$bestIdx]->num_int ?? null) : null;
        return [$num, $bestIdx];
    }

    public function nextNumInt(string $agence, Carbon $date): string
    {
        $agence = trim($agence);
        if ($agence === '' || !preg_match('/^[A-Za-z0-9_-]{3,6}$/', $agence)) {
            throw new \InvalidArgumentException('Code agence invalide.');
        }

        $yymm   = $date->format('ym');          // ex: 2510
        $prefix = $agence . '-' . $yymm . '-';  // ex: M44N-2510-

        $last = DB::table('t_intervention')
            ->where('NumInt', 'like', $prefix.'%')
            ->max('NumInt'); // lexicographique OK vu le padding

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $tail  = end($parts) ?: '00000';
            $seq   = (int) $tail + 1;
        }

        $seqStr = str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
        return $prefix.$seqStr;
    }


}
