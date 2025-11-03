<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PlanningService
{
    /**
     * Retourne le planning technicien (ou _ALL) entre deux dates, ou sur N jours.
     *
     * @param string      $codeTech      Code technicien ou "_ALL"
     * @param string|null $from          YYYY-MM-DD (optionnel)
     * @param string|null $to            YYYY-MM-DD (optionnel)
     * @param int|null    $days          Nombre de jours si from/to absents (par défaut 5, borné 1..60)
     * @param string      $tz            Timezone (par défaut Europe/Paris)
     * @param array|null  $allowedTechs  Liste blanche de codes techniciens quand $codeTech === "_ALL" (optionnel)
     * @return array{ok:bool,from:string,to:string,events:array<int,array<string,mixed>>}
     */
    public function getPlanning(
        $codeTech,
        $from = null,
        $to = null,
        $days = null,
        $tz = 'Europe/Paris',
        ?array $allowedCodes = null
    ) {
        $range = $this->computeRange($from, $to, $days, $tz);
        $start = $range['start']->toDateString();
        $end   = $range['end']->toDateString();

        // Normalisation whitelist
        $allowed = array_values(array_unique(array_map(
            fn($c) => strtoupper(trim((string)$c)), $allowedCodes ?? []
        )));
        $exacts   = array_values(array_filter($allowed, fn($c) => strpos($c, '*') === false));
        $prefixes = array_values(array_map(
            fn($c) => rtrim($c, '*'),
            array_filter($allowed, fn($c) => substr($c, -1) === '*')
        ));

        $q = DB::table('t_planning_technicien as p')
            ->select(
                'p.id as rid',
                DB::raw('TRIM(UPPER(p.CodeTech)) as CodeTech'),
                'p.StartDate','p.StartTime','p.EndDate','p.EndTime',
                'p.NumIntRef','p.Label','p.IsValidated','p.Commentaire',
                'p.CPLivCli','p.VilleLivCli','p.IsUrgent',
                'i.Marque','e.contact_reel as Contact'
            )
            ->leftJoin('t_actions_etat as e', 'e.NumInt', '=', 'p.NumIntRef')
            ->leftJoin('t_intervention as i', 'i.NumInt', '=', 'p.NumIntRef')
            ->whereBetween('p.StartDate', [$start, $end])
            ->orderBy('p.StartDate')->orderBy('p.StartTime');

        // 1) Tech précis => on montre tout son agenda (normalisé)
        if ($codeTech !== '_ALL') {
            $q->where(DB::raw('TRIM(UPPER(p.CodeTech))'), '=', strtoupper(trim((string)$codeTech)));

            // Option sécurité : si whitelist fournie et que le tech n’y est pas, on retourne vide.
            if (!empty($allowed) && !self::matchesAllowed(strtoupper(trim((string)$codeTech)), $exacts, $prefixes)) {
                return ['ok'=>true,'from'=>$start,'to'=>$end,'events'=>[]];
            }
        }
        // 2) Mode _ALL => restreint aux personnes autorisées (exacts + wildcards)
        else {
            if (!empty($exacts) || !empty($prefixes)) {
                $q->where(function($w) use ($exacts, $prefixes) {
                    if (!empty($exacts)) {
                        $w->whereIn(DB::raw('TRIM(UPPER(p.CodeTech))'), $exacts);
                    }
                    if (!empty($prefixes)) {
                        $w->orWhere(function($or) use ($prefixes) {
                            foreach ($prefixes as $pfx) {
                                $or->orWhere(DB::raw('TRIM(UPPER(p.CodeTech))'), 'like', $pfx.'%');
                            }
                        });
                    }
                });
            } else {
                // Pas d’autorisés => rien à afficher
                return ['ok'=>true,'from'=>$start,'to'=>$end,'events'=>[]];
            }
        }

        $rows = $q->get();

        $events = [];
        foreach ($rows as $r) {
            $startIso = $r->StartDate.'T'.($r->StartTime ?: '00:00:00');
            $endIso   = !empty($r->EndDate) ? ($r->EndDate.'T'.($r->EndTime ?: '00:00:00')) : null;

            $events[] = [
                'id' => $r->rid,
                'code_tech'      => $r->CodeTech, // déjà TRIM/UPPER
                'start_datetime' => $startIso,
                'end_datetime'   => $endIso,
                'label'          => $r->Label,
                'num_int'        => $r->NumIntRef,
                'contact'        => $r->Contact ?: null,
                'is_validated'   => isset($r->IsValidated) ? ((int)$r->IsValidated === 1) : null,
                'commentaire'    => $r->Commentaire ?: null,
                'cp'             => $r->CPLivCli ?: null,
                'ville'          => $r->VilleLivCli ?: null,
                'marque'         => $r->Marque ?: null,
                'is_urgent'      => (int)$r->IsUrgent === 1,
            ];
        }

        return ['ok'=>true,'from'=>$start,'to'=>$end,'events'=>$events];
    }

    private static function matchesAllowed(string $code, array $exacts, array $prefixes): bool
    {
        if (in_array($code, $exacts, true)) return true;
        foreach ($prefixes as $p) {
            if (str_starts_with($code, $p)) return true;
        }
        return false;
    }



    /**
     * Détermine la plage start/end à partir de from/to ou days.
     * @return array{start:Carbon, end:Carbon}
     */
    private function computeRange($from, $to, $days, $tz = 'Europe/Paris')
    {
        try {
            if (!empty($from) && !empty($to)) {
                $start = Carbon::parse($from, $tz)->startOfDay();
                $end   = Carbon::parse($to,   $tz)->endOfDay();
                return ['start' => $start, 'end' => $end];
            }
        } catch (\Throwable $e) {
            // En cas de date invalide, on retombe sur le mode "days"
        }

        $d = (int) $days;
        if ($d <= 0)  $d = 5;
        if ($d > 60)  $d = 60;

        $start = Carbon::now($tz)->startOfDay();
        $end   = $start->copy()->addDays($d)->endOfDay();

        return ['start' => $start, 'end' => $end];
    }

    public function listTempsByNumInt(string $numInt): Collection
    {
        return DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where(function ($w) {
                $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
            })
            ->orderBy('StartDate')->orderBy('StartTime')
            ->get(['id','CodeTech','StartDate','StartTime','Label']);
    }
}
