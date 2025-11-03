<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessInterventionService
{
    public function agenceFromNumInt(string $numInt): string
    {
        $base = explode('-', $numInt, 2)[0] ?? '';
        return mb_substr($base, 0, 4);
    }

    /**
     * Priorité : interne > direction > externe
     * Retour : Collection d'objets { CodeSal, NomSal, CodeAgSal, access_level }
     */
    public function listPeopleForNumInt(string $numInt): Collection
    {
        $ag = $this->agenceFromNumInt($numInt);
        $startMC = preg_match('/^[MC]/', $ag) === 1;


        // petit helper pour le test Obsolete (NULL autorisé)
       /*
        $notObsolete = function ($q) {
            $q->whereNull('s.Obsolete')->orWhere('s.Obsolete', '!=', 'O');
        };
       */

        // --- Interne ---
        $qInterne = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'interne' as access_level")
            //->where($notObsolete)
            ->where('s.CodeAgSal', $ag);

        // --- Direction (PLUS + t_resp Defaut = 'O') ---
        $qDirection = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'direction' as access_level")
            //->where($notObsolete)
            ->where('s.CodeAgSal', 'PLUS')
            ->whereExists(function ($q) use ($ag) {
                $q->from('t_resp as r')
                    ->whereColumn('r.CodeSal', 's.CodeSal')
                    ->where('r.CodeAgSal', $ag)
                    ->where('r.Defaut', 'O');
            });

        // --- Externe ---
        // (i) PLUS + t_resp Defaut = 'N'
        $qExtPlus = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'externe' as access_level")
            //->where($notObsolete)
            ->where('s.CodeAgSal', 'PLUS')
            ->whereExists(function ($q) use ($ag) {
                $q->from('t_resp as r')
                    ->whereColumn('r.CodeSal', 's.CodeSal')
                    ->where('r.CodeAgSal', $ag)
                    ->where('r.Defaut', 'N');
            });

        // (ii) DOAG si agence commence par M ou C
        $qExtDoag = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'externe' as access_level")
            //->where($notObsolete)
            ->when($startMC, fn($q) => $q->where('s.CodeAgSal', 'DOAG'), fn($q) => $q->whereRaw('0=1'));

        // (iii) Tous les ADMI
        $qExtAdmi = DB::table('t_salarie as s')
            ->selectRaw("s.CodeSal, s.NomSal, s.CodeAgSal, 'externe' as access_level")
            //->where($notObsolete)
            ->where('s.CodeAgSal', 'ADMI');

        // UNION (sans fromSub / DB::query) + tri global
        $union = $qInterne
            ->unionAll($qDirection)
            ->unionAll($qExtPlus)
            ->unionAll($qExtDoag)
            ->unionAll($qExtAdmi);

        // L'ORDER BY placé ici s'applique à tout le UNION dans MySQL
        $rows = $union
            ->orderByRaw("FIELD(access_level, 'interne','direction','externe')")
            ->orderBy('NomSal')
            ->get();

        // Dédoublonnage par CodeSal avec priorité interne > direction > externe
        $priority = ['interne' => 3, 'direction' => 2, 'externe' => 1];

        return collect($rows)
            ->groupBy('CodeSal')
            ->map(function ($grp) use ($priority) {
                return $grp->sortByDesc(fn($r) => $priority[$r->access_level] ?? 0)->first();
            })
            ->values();
    }

    public function listAgendaPeopleForNumInt(string $numInt, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $ag    = $this->agenceFromNumInt($numInt);         // ← agence du dossier
        $base  = $this->listPeopleForNumInt($numInt);      // accès autorisé (interne/direction/externe)
        if ($base->isEmpty()) return collect();

        // Normalisation des codes (TRIM + UPPER) pour éviter FABE vs "FABE "
        $codes = $base->pluck('CodeSal')->map(fn($c)=>strtoupper(trim((string)$c)))->all();

        // Fenêtre par défaut : aujourd’hui -> +60 jours
        $from = $from ?: Carbon::now('Europe/Paris')->startOfDay();
        $to   = $to   ?: (clone $from)->addDays(60)->endOfDay();

        // RDV "dans la fenêtre" (toutes agences)
        $rdvAny = DB::table('t_planning_technicien as p')
            ->selectRaw('UPPER(TRIM(p.CodeTech)) as c')
            ->whereBetween('p.StartDate', [$from->toDateString(), $to->toDateString()])
            ->whereIn(DB::raw('UPPER(TRIM(p.CodeTech))'), $codes)
            ->distinct()
            ->pluck('c')
            ->flip();   // Set: code => true

        // RDV "même agence que le dossier" (M31T-xxxx)
        $rdvSameAg = DB::table('t_planning_technicien as p')
            ->selectRaw('UPPER(TRIM(p.CodeTech)) as c')
            ->whereBetween('p.StartDate', [$from->toDateString(), $to->toDateString()])
            ->where('p.NumIntRef', 'like', $ag.'-%')
            ->whereIn(DB::raw('UPPER(TRIM(p.CodeTech))'), $codes)
            ->distinct()
            ->pluck('c')
            ->flip();

        // Flags TECH
        $techFlags = DB::table('t_salarie as s')
            ->selectRaw("UPPER(TRIM(s.CodeSal)) as c, CASE WHEN UPPER(s.fonction) IN ('TECH','TECHNICIEN') THEN 1 ELSE 0 END as tech_score")
            ->whereIn(DB::raw('UPPER(TRIM(s.CodeSal))'), $codes)
            ->get()
            ->keyBy('c');

        // Règle finale :
        //  - garde si TECH
        //  - OU (access_level ∈ {interne,direction} ET hasRdvAny)
        //  - OU hasRdvSameAgency (quel que soit access_level)
        return $base->map(function ($p) use ($techFlags, $rdvAny, $rdvSameAg) {
            $code = strtoupper(trim((string)$p->CodeSal));
            $isTech = (int)($techFlags[$code]->tech_score ?? 0) > 0;
            $hasAny = $rdvAny->has($code);
            $hasAg  = $rdvSameAg->has($code);

            $keep = $isTech
                || (in_array($p->access_level, ['interne','direction'], true) && $hasAny)
                || $hasAg;

            if (!$keep) return null;

            return (object)[
                'CodeSal'      => $p->CodeSal,
                'NomSal'       => $p->NomSal,
                'CodeAgSal'    => $p->CodeAgSal,
                'access_level' => $p->access_level,
                'is_tech'      => $isTech,
                'has_rdv'      => $hasAny,
                'has_rdv_ag'   => $hasAg, // debug/help
            ];
        })
            ->filter()
            // tri : TECH (0) > direction (1) > interne (2) > reste (3), puis Nom
            ->sort(function ($a, $b) {
                $rank = fn($x) => $x->is_tech ? 0 : ($x->access_level === 'direction' ? 1 : ($x->access_level === 'interne' ? 2 : 3));
                $ra = $rank($a); $rb = $rank($b);
                return ($ra === $rb) ? strcasecmp($a->NomSal ?? '', $b->NomSal ?? '') : ($ra <=> $rb);
            })
            ->values();
    }

}
