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
    public function getPlanning($codeTech, $from = null, $to = null, $days = null, $tz = 'Europe/Paris', $allowedTechs = null)
    {
        // 1) Détermine la plage de dates
        $range = $this->computeRange($from, $to, $days, $tz);
        $start = $range['start']; // Carbon
        $end   = $range['end'];   // Carbon

        // 2) Requête unique
        $q = DB::table('t_planning_technicien as p')
            ->select(
                'p.id as rid', 'p.CodeTech',
                'p.StartDate', 'p.StartTime',
                'p.EndDate',   'p.EndTime',
                'p.NumIntRef',
                'p.Label',
                'p.IsValidated',
                'p.Commentaire',        // NEW
                'p.CPLivCli',           // NEW
                'p.VilleLivCli',        // NEW
                'p.IsUrgent',           // <-- AJOUT
                'i.Marque',             // NEW
                'e.contact_reel as Contact'
            )
            ->leftJoin('t_actions_etat as e', 'e.NumInt', '=', 'p.NumIntRef')
            ->whereBetween('p.StartDate', [$start->toDateString(), $end->toDateString()])
            ->leftJoin('t_intervention as i', 'i.NumInt', '=', 'p.NumIntRef') // NEW
            ->whereBetween('p.StartDate', [$start->toDateString(), $end->toDateString()])
            ->orderBy('p.StartDate')
            ->orderBy('p.StartTime');

        if ($codeTech !== '_ALL') {
            $q->where('p.CodeTech', $codeTech);
        } elseif (is_array($allowedTechs) && !empty($allowedTechs)) {
            // Optionnel: filtrer _ALL avec une liste blanche
            $q->whereIn('p.CodeTech', $allowedTechs);
        }

        $rows = $q->get();

        // 3) Mapping
        $events = [];
        foreach ($rows as $row) {
            $startIso = $row->StartDate . 'T' . ($row->StartTime ?: '00:00:00');
            $endIso   = !empty($row->EndDate) ? ($row->EndDate . 'T' . ($row->EndTime ?: '00:00:00')) : null;

            $events[] = [
                'id' => $row->rid,
                'code_tech'      => $row->CodeTech,
                'start_datetime' => $startIso,
                'end_datetime'   => $endIso,
                'label'          => $row->Label,
                'num_int'        => $row->NumIntRef,
                'contact'        => $row->Contact ?: null,
                'is_validated'   =>  isset($row->IsValidated) ? ((int)$row->IsValidated === 1) : null,
                'commentaire'    => $row->Commentaire ?: null,      // NEW
                'cp'             => $row->CPLivCli ?: null,         // NEW
                'ville'          => $row->VilleLivCli ?: null,      // NEW
                'marque'         => $row->Marque ?: null,
                'is_urgent' => (int)$row->IsUrgent === 1,// NEW
            ];
        }

        return [
            'ok'     => true,
            'from'   => $start->format('Y-m-d'),
            'to'     => $end->format('Y-m-d'),
            'events' => $events,
        ];
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
