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


    /**
     * N'affiche que les interventions dont le NumInt commence par une agence autorisée.
     * - client        = contact_reel (fallback "(contact inconnu)")
     * - a_faire       = t_actions_vocabulaire.label (fallback objet_traitement, sinon "À préciser")
     * - date/heure    = COALESCE(tech_rdv_at, rdv_prev_at)
     * - urgent        = ae.urgent
     * - concerne      = (ae.reaffecte_code == $codeSal)
     * - rdv_validé    = (ae.tech_rdv_at non NULL)
     * - is_site       = (ti.LieuInt LIKE 'site%')
     * - tri           = tier asc, COALESCE(tech_rdv_at, rdv_prev_at) (NULL en dernier), NumInt asc
     *
     * Filtrage agence :
     * - $agencesAutorisees = liste d'agences whitelistées (préfixes de NumInt)
     * - $selectedAgence (optionnel) = si fourni et ∈ $agencesAutorisees, on filtre uniquement sur celle-ci
     *
     * NOTE : pas de filtre obligatoire sur reaffecte_code/rdv_prev_at → on les affiche même vides,
     *        notamment les interventions "site%" non assignées.
     */
    public function listPaginatedSimple(
        int     $perPage = 25,
        array   $agencesAutorisees = [],
        ?string $codeSal = null,
        ?string $q = null,
        ?string $scope = null,
        ?string $selectedAgence = null
    ): LengthAwarePaginator
    {
        // --- normalise et whiteliste les agences autorisées
        $toFilter = [];
        foreach ((array)$agencesAutorisees as $ag) {
            $ag = strtoupper(trim((string)$ag));
            if ($ag !== '' && preg_match('/^[A-Z0-9_-]{2,8}$/', $ag)) {
                $toFilter[] = $ag;
            }
        }
        $toFilter = array_values(array_unique($toFilter));

        if (empty($toFilter)) {
            return new LengthAwarePaginator(collect(), 0, $perPage);
        }

        // '_ALL' -> ignoré ici (contrôleur devrait passer null). On re-sécurise au cas où.
        if ($selectedAgence === '_ALL') {
            $selectedAgence = null;
        }
        if ($selectedAgence) {
            $sel = strtoupper(trim($selectedAgence));
            if (in_array($sel, $toFilter, true)) {
                $toFilter = [$sel];
            }
        }

        // --- sanitisation "q" défensive
        $q = is_string($q) ? trim($q) : null;
        if ($q !== null) {
            $q = preg_replace('/[\x00-\x1F\x7F]/u', '', $q);
            $q = str_replace(['<','>'], '', $q);
            $q = mb_substr($q, 0, 120);
            if ($q === '') $q = null;
        }

        // COALESCE date affichée
        $dtCoalesce = "COALESCE(ae.tech_rdv_at, ae.rdv_prev_at)";

        $query = DB::table('t_actions_etat as ae')
            ->leftJoin('t_actions_vocabulaire as v', function ($join) {
                $join->on('v.code', '=', 'ae.objet_traitement')
                    ->where('v.group_code', '=', 'AFFECTATION');
            })
            ->leftJoin('t_intervention as ti', 'ti.NumInt', '=', 'ae.NumInt')
            ->selectRaw("
                ae.NumInt AS num_int,

                COALESCE(NULLIF(ae.contact_reel,''), '(contact inconnu)') AS client,

                DATE($dtCoalesce) AS date_prev,
                TIME($dtCoalesce) AS heure_prev,

                ae.reaffecte_code,
                ae.urgent,

                CASE WHEN ae.reaffecte_code = ? THEN 1 ELSE 0 END AS concerne,

                v.code  AS a_faire_code,
                COALESCE(NULLIF(v.label,''), COALESCE(NULLIF(ae.objet_traitement,''), 'À préciser')) AS a_faire_label,

                ti.Marque      AS marque,
                ti.VilleLivCli AS ville,
                ti.CPLivCli    AS cp,
                ae.commentaire AS commentaire,

                -- Nouveaux champs/flags
                ae.tech_rdv_at           AS tech_rdv_at,
                CASE WHEN ae.tech_rdv_at IS NOT NULL THEN 1 ELSE 0 END AS rdv_valide,
                ti.LieuInt               AS lieu_int,
                CASE WHEN ti.LieuInt LIKE 'site%' THEN 1 ELSE 0 END AS is_site,

                CASE
                  WHEN ae.urgent=1 AND ae.reaffecte_code = ? THEN 0
                  WHEN ae.urgent=1 THEN 1
                  WHEN ae.reaffecte_code = ? THEN 2
                  ELSE 3
                END AS tier
            ", [$codeSal, $codeSal, $codeSal]);

        // --- Filtre agences (par préfixe de NumInt)
        $query->where(function ($qW) use ($toFilter) {
            foreach ($toFilter as $i => $ag) {
                $pattern = $ag . '%';
                if ($i === 0) $qW->where('ae.NumInt', 'like', $pattern);
                else          $qW->orWhere('ae.NumInt', 'like', $pattern);
            }
        });

        // --- Scope (on conserve la logique existante)
        $scopeNorm = $scope ? strtolower($scope) : null;
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
            $safe = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q);
            $like = "%{$safe}%";
            $query->where(function ($w) use ($like) {
                $w->where('ae.NumInt', 'like', $like)
                    ->orWhere('ae.contact_reel', 'like', $like)
                    ->orWhere('v.label', 'like', $like)
                    ->orWhere('ae.objet_traitement', 'like', $like)
                    ->orWhere('ti.VilleLivCli', 'like', $like)
                    ->orWhere('ti.CPLivCli', 'like', $like);
            });
        }

        // --- Tri : priorité (tier) puis COALESCE(tech_rdv_at, rdv_prev_at), puis NumInt
        $query->orderBy('tier', 'asc')
            ->orderByRaw("$dtCoalesce IS NULL ASC")
              ->orderByRaw("$dtCoalesce ASC")
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
