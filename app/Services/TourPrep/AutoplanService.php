<?php
namespace App\Services\TourPrep;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AutoplanService
{
    private const LIMIT_S = 6 * 3600; // 6h max trajets (retour agence inclus), sauf cas 1 stop

    private DistanceServiceFast $fast;
    private DistanceServiceOsrm $osrm;
    private GeocodeService $geo;

    public function __construct(DistanceServiceFast $fast, DistanceServiceOsrm $osrm, GeocodeService $geo)
    {
        $this->fast = $fast;
        $this->osrm = $osrm;
        $this->geo  = $geo;
    }

    public function generateProposal(string $agRef, string $date, string $mode = 'fast', bool $opt = true): array
    {
        // 1) Agence
        $ag = DB::table('agence')->where('Code_ag', $agRef)->first();
        if (!$ag) throw new \RuntimeException('Agence introuvable pour ' . $agRef);

        $startLabel = trim(implode(' ', array_filter([
            (string)($ag->AdrAg1 ?? ''), (string)($ag->AdrAg2 ?? ''),
            trim((string)($ag->CpAg ?? '') . ' ' . (string)($ag->VilleAg ?? ''))
        ])));
        if ($startLabel === '' && !empty($ag->VilleAg)) {
            $startLabel = trim((string)($ag->CpAg ?? '') . ' ' . (string)$ag->VilleAg);
        }
        $agency = $this->geo->geocode($startLabel);
        if (!$agency) throw new \RuntimeException('Impossible de géocoder l’adresse agence.');

        // 2) RDV du jour
        $rows = DB::table('t_intervention as ti')
            ->leftJoin('t_actions_etat as tae', 'tae.NumInt', '=', 'ti.NumInt')
            ->select(
                'ti.NumInt','ti.CodeTech','ti.DateIntPrevu','ti.HeureIntPrevu',
                'ti.AdLivCli','ti.CPLivCli','ti.VilleLivCli','ti.Marque','ti.TypeApp',
                DB::raw('COALESCE(tae.urgent,0) as urgent'),
                'tae.commentaire as commentaire',
                DB::raw("EXISTS(SELECT 1 FROM t_planning_technicien p WHERE p.NumIntRef=ti.NumInt AND p.isObsolete=0 AND p.IsValidated=1) as has_validated"),
                DB::raw("EXISTS(SELECT 1 FROM t_planning_technicien p2 WHERE p2.NumIntRef=ti.NumInt AND p2.isObsolete=0 AND (p2.IsValidated IS NULL OR p2.IsValidated=0)) as has_temp")
            )
            ->whereDate('ti.DateIntPrevu', $date)
            ->whereRaw('LEFT(ti.NumInt,4)=?', [$agRef])
            ->whereNotNull('ti.CodeTech')
            ->orderBy('ti.CodeTech')
            ->get();

        if ($rows->isEmpty()) {
            return ['assignments' => [], 'meta' => ['note' => 'Aucun RDV']];
        }

        // 3) Géocodage stops
        $stops = [];
        foreach ($rows as $r) {
            $addr = trim(implode(' ', array_filter([(string)($r->CPLivCli ?? ''), (string)($r->VilleLivCli ?? '')])));
            $g = $this->geo->geocode($addr);
            if (!$g) continue;
            $stops[] = [
                'numint'   => (string)$r->NumInt,
                'tech'     => (string)$r->CodeTech,
                'cp'       => (string)($r->CPLivCli ?? ''),
                'city'     => (string)($r->VilleLivCli ?? ''),
                'lat'      => (float)$g['lat'],
                'lon'      => (float)$g['lon'],
                'urgent'   => (int)$r->urgent,
                'has_temp' => (int)$r->has_temp,
                'has_validated' => (int)$r->has_validated,
            ];
        }

        // 4) Groupement par tech
        $byTech = [];
        foreach ($stops as $s) { $byTech[$s['tech']][] = $s; }
        $techCodes = array_keys($byTech);
        $metaTech = $this->loadTechMeta($techCodes, $agRef);

        // 5) Ordonnancement initial
        $routes = [];
        foreach ($byTech as $tech => $list) {
            $routes[$tech] = $this->nearestNeighborRoute($agency, $list, $mode);
        }

        // 6) Compaction ≤ 6h (retour inclus), avec garde fou pour cas 1 stop
        $this->compactRoutes($routes, $agency, $metaTech, $mode);

        // 7) Ajout de tech et déchargement tant qu’il existe une tournée >6h avec ≥2 stops
        while ($this->anyOver6hNeedingSplit($routes, $agency, $mode)) {
            $extra = $this->pickExtraTech($agRef, array_keys($routes));
            if (!$extra) break; // plus de ressources
            $routes[$extra] = [];
            $this->lightenHeaviestInto($routes, $agency, $extra, $mode);
        }

        // 8) Assignments + horaires
        $assignments = $this->buildAssignmentsWithSchedule($routes, $agency, $date, $mode);

        // 9) Meta
        $perTech = [];
        foreach ($routes as $tech => $list) {
            $perTech[$tech] = [
                'stops'      => count($list),
                'travel_s'   => $this->routeTravelSeconds($agency, $list, $mode, true),
                'internal'   => (bool)($metaTech[$tech]['internal'] ?? false),
            ];
        }

        return [
            'assignments' => $assignments,
            'meta' => [
                'per_tech'        => $perTech,
                'total_travel_s'  => array_sum(array_column($perTech, 'travel_s')),
            ],
        ];
    }

    /* ---------------- Helpers ---------------- */

    private function loadTechMeta(array $codes, string $agRef): array
    {
        if (empty($codes)) return [];
        $rows = DB::table('t_salarie')
            ->whereIn('CodeSal', $codes)
            ->select('CodeSal','CodeAgSal')
            ->get();
        $meta = [];
        foreach ($rows as $r) {
            $meta[$r->CodeSal] = ['internal' => (strtoupper((string)$r->CodeAgSal) === strtoupper($agRef))];
        }
        foreach ($codes as $c) {
            if (!isset($meta[$c])) $meta[$c] = ['internal' => false];
        }
        return $meta;
    }

    private function nearestNeighborRoute(array $agency, array $stops, string $mode): array
    {
        $rem = $stops;
        $route = [];
        $cur = $agency;
        while (!empty($rem)) {
            $bestIdx = null;
            $best = PHP_INT_MAX;
            foreach ($rem as $i => $s) {
                $d = $this->fast->distanceMeters($cur['lat'], $cur['lon'], $s['lat'], $s['lon']);
                if ($d < $best) { $best = $d; $bestIdx = $i; }
            }
            $route[] = $rem[$bestIdx];
            $cur = ['lat'=>$rem[$bestIdx]['lat'],'lon'=>$rem[$bestIdx]['lon']];
            array_splice($rem, $bestIdx, 1);
        }
        return $route;
    }

    private function durationSeconds(float $lat1, float $lon1, float $lat2, float $lon2, string $mode): int
    {
        return $this->fast->durationSeconds($lat1, $lon1, $lat2, $lon2, 45);
    }

    /** Total trajets, avec ou sans retour agence. */
    private function routeTravelSeconds(array $agency, array $route, string $mode, bool $withReturn = true): int
    {
        if (empty($route)) return 0;
        $tot = 0;
        $prev = $agency;
        foreach ($route as $s) {
            $tot += $this->durationSeconds($prev['lat'], $prev['lon'], $s['lat'], $s['lon'], $mode);
            $prev = ['lat'=>$s['lat'],'lon'=>$s['lon']];
        }
        if ($withReturn) {
            $tot += $this->durationSeconds($prev['lat'], $prev['lon'], $agency['lat'], $agency['lon'], $mode);
        }
        return $tot;
    }

    /** On demande un split seulement si >6h **et** ≥2 stops (1 stop >6h = toléré). */
    private function anyOver6hNeedingSplit(array $routes, array $agency, string $mode): bool
    {
        foreach ($routes as $rt) {
            if (count($rt) >= 2 && $this->routeTravelSeconds($agency, $rt, $mode, true) > self::LIMIT_S) {
                return true;
            }
        }
        return false;
    }

    /** Compaction : recase des petites tournées sans jamais dépasser 6h (retour inclus). */
    private function compactRoutes(array &$routes, array $agency, array $metaTech, string $mode): void
    {
        if (count($routes) <= 1) return;

        while (true) {
            // charges (retour inclus)
            $load = [];
            foreach ($routes as $tech => $rt) {
                $load[$tech] = $this->routeTravelSeconds($agency, $rt, $mode, true);
            }
            asort($load);

            // source = le premier ayant au moins 1 stop
            $src = null;
            foreach ($load as $tech => $secs) {
                if (!empty($routes[$tech])) { $src = $tech; break; }
            }
            if ($src === null) break;

            // cibles (autres techs), internes d’abord puis plus légers
            $targets = array_values(array_filter(array_keys($routes), fn($t)=> $t !== $src));
            if (empty($targets)) break;

            usort($targets, function($a,$b) use($metaTech,$load){
                $ia = $metaTech[$a]['internal'] ?? false;
                $ib = $metaTech[$b]['internal'] ?? false;
                if ($ia !== $ib) return $ia ? -1 : 1;
                return ($load[$a] ?? 0) <=> ($load[$b] ?? 0);
            });

            $srcStops = $routes[$src];
            if (empty($srcStops)) { unset($routes[$src]); continue; }

            $movedAll = true;
            foreach ($srcStops as $stop) {
                $bestT = null; $bestPos = null; $bestDelta = PHP_INT_MAX; $bestTotal = PHP_INT_MAX;

                foreach ($targets as $tgt) {
                    [$pos, $delta, $newTotal] = $this->bestInsertion($routes[$tgt], $agency, $stop, $mode);
                    // n’accepte que si le receveur reste ≤ 6h, ou (cas particulier) si receveur n’aurait qu’1 stop après insertion
                    $futureCount = count($routes[$tgt]) + 1;
                    $okOnLimit = ($futureCount === 1) ? true : ($newTotal <= self::LIMIT_S);
                    if ($okOnLimit && $delta < $bestDelta) {
                        $bestDelta = $delta; $bestT = $tgt; $bestPos = $pos; $bestTotal = $newTotal;
                    }
                }

                if ($bestT === null) { $movedAll = false; break; }

                array_splice($routes[$bestT], $bestPos, 0, [$stop]);

                // retirer du src
                $idx = array_search($stop, $routes[$src], true);
                if ($idx !== false) array_splice($routes[$src], $idx, 1);
            }

            if ($movedAll) { unset($routes[$src]); }
            else { break; }
        }
    }

    /** Teste toutes les positions d’insertion et renvoie [pos, delta, new_total] (retour inclus). */
    private function bestInsertion(array $route, array $agency, array $stop, string $mode): array
    {
        $base = $this->routeTravelSeconds($agency, $route, $mode, true);
        $n = count($route);

        $bestPos = 0; $bestDelta = PHP_INT_MAX; $bestTotal = PHP_INT_MAX;
        for ($i = 0; $i <= $n; $i++) {
            $candidate = $route;
            array_splice($candidate, $i, 0, [$stop]);
            $newTotal = $this->routeTravelSeconds($agency, $candidate, $mode, true);
            $delta = $newTotal - $base;
            if ($delta < $bestDelta) {
                $bestDelta = $delta; $bestPos = $i; $bestTotal = $newTotal;
            }
        }
        return [$bestPos, $bestDelta, $bestTotal];
    }

    private function pickExtraTech(string $agRef, array $already): ?string
    {
        $rows = DB::table('t_salarie')
            ->select('CodeSal','CodeAgSal')
            ->orderByRaw("CASE WHEN CodeAgSal = ? THEN 0 ELSE 1 END", [$agRef])
            ->orderBy('CodeSal')
            ->get();

        foreach ($rows as $r) {
            $c = (string)$r->CodeSal;
            if (!in_array($c, $already, true)) return $c;
        }
        return null;
    }

    /** Décharge le plus chargé vers $receiverTech sans faire dépasser 6h (sauf cas receveur = 1 stop). */
    private function lightenHeaviestInto(array &$routes, array $agency, string $receiverTech, string $mode): void
    {
        // trouver donneur (le plus chargé en tenant compte du retour)
        $heaviest = null; $max = -1;
        foreach ($routes as $tech => $rt) {
            $t = $this->routeTravelSeconds($agency, $rt, $mode, true);
            if ($t > $max) { $max = $t; $heaviest = $tech; }
        }
        if ($heaviest === null || empty($routes[$heaviest])) return;

        $donor = &$routes[$heaviest];
        while (!empty($donor)) {
            $donorLoad = $this->routeTravelSeconds($agency, $donor, $mode, true);
            // on s’arrête si (a) donneur ≤ 6h ou (b) il ne lui reste déjà qu’un stop (cas toléré >6h)
            if ($donorLoad <= self::LIMIT_S || count($donor) <= 1) break;

            $stop = array_pop($donor);

            [$pos, $delta, $newTotal] = $this->bestInsertion($routes[$receiverTech], $agency, $stop, $mode);
            $futureCount = count($routes[$receiverTech]) + 1;
            $okOnLimit = ($futureCount === 1) ? true : ($newTotal <= self::LIMIT_S);

            if ($okOnLimit) {
                $routes[$receiverTech] = array_merge(
                    array_slice($routes[$receiverTech], 0, $pos),
                    [$stop],
                    array_slice($routes[$receiverTech], $pos)
                );
            } else {
                // si même l’insertion “optimale” dépasse 6h avec ≥2 stops, on ne force pas
                break;
            }
        }
    }

    private function buildAssignmentsWithSchedule(array $routes, array $agency, string $date, string $mode): array
    {
        $assignments = [];
        foreach ($routes as $tech => $rt) {
            $cur = Carbon::parse($date . ' 08:00:00', 'Europe/Paris');
            $pos = $agency;

            foreach ($rt as $s) {
                $tsec = $this->durationSeconds($pos['lat'],$pos['lon'],$s['lat'],$s['lon'],$mode);
                $arrival = $cur->copy()->addSeconds($tsec);

                $start = $arrival->copy()->addMinutes(5)->second(0);
                $m = (int)$start->minute;
                $roundUp = ($m % 10) ? (10 - ($m % 10)) : 0;
                if ($roundUp > 0) $start->addMinutes($roundUp);

                $assignments[] = [
                    'numInt'   => $s['numint'],
                    'fromTech' => $s['tech'],
                    'toTech'   => $tech,
                    'rdv_at'   => $start->format('Y-m-d H:i:s'),
                ];

                $cur = $start->copy()->addMinutes(30);
                $pos = ['lat'=>$s['lat'],'lon'=>$s['lon']];
            }
        }
        return $assignments;
    }
}
