<?php
// app/Http/Controllers/TourPrepController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TourPrep\GeocodeService;
use App\Services\TourPrep\DistanceServiceFast;
use App\Services\TourPrep\DistanceServiceOsrm;
use App\Services\TourPrep\TourBuilder;

class TourPrepController extends Controller
{
    public function show(Request $req)
    {
        $date  = $req->query('date', date('Y-m-d'));
        $agRef = $req->query('agref');           // ex: M59L
        $mode  = $req->query('mode', 'fast');    // fast | precise
        $opt   = (bool) $req->query('opt', 0);   // 0|1

        if (!$agRef) {
            return response('Paramètre agref manquant (ex: ?agref=M44N&date=2025-11-05)', 400);
        }

        // Adresse de départ = agence
        $ag = DB::table('agence')->where('Code_ag', $agRef)->first();
        if (!$ag) return response('Agence introuvable pour Code_ag='.$agRef, 404);

        $parts = array_filter([
            trim((string)($ag->AdrAg1 ?? '')),
            trim((string)($ag->AdrAg2 ?? '')),
            trim((string)($ag->CpAg ?? '') . ' ' . (string)($ag->VilleAg ?? '')),
        ]);
        $startLabel = trim(implode(' ', $parts));
        if ($startLabel === '' && !empty($ag->VilleAg)) {
            $startLabel = trim((string)($ag->CpAg ?? '') . ' ' . (string)$ag->VilleAg);
        }

        $geo = new GeocodeService();
        $startGeo = $geo->geocode($startLabel);
        if (!$startGeo) {
            return response('Impossible de géocoder l’adresse agence ('.$startLabel.')', 500);
        }

        // RDV du jour rattachés à cette agence (via préfixe NumInt) + urgence/commentaire
        $rows = DB::table('t_intervention as ti')
            ->leftJoin('t_actions_etat as tae', 'tae.NumInt', '=', 'ti.NumInt')
            ->select(
                'ti.NumInt',
                'ti.CodeTech',
                'ti.DateIntPrevu',
                'ti.HeureIntPrevu',
                'ti.AdLivCli','ti.CPLivCli','ti.VilleLivCli','ti.TypeApp','ti.Marque',
                DB::raw('COALESCE(tae.urgent, 0) as urgent'),
                'tae.commentaire as commentaire',
                // ⬇️ drapeaux pour la vue
                DB::raw("EXISTS(SELECT 1 FROM t_planning_technicien p
                    WHERE p.NumIntRef=ti.NumInt AND p.isObsolete=0 AND p.IsValidated=1) as has_validated"),
                DB::raw("EXISTS(SELECT 1 FROM t_planning_technicien p2
                    WHERE p2.NumIntRef=ti.NumInt AND p2.isObsolete=0 AND (p2.IsValidated IS NULL OR p2.IsValidated=0)) as has_temp")
            )
            ->whereDate('ti.DateIntPrevu', $date)
            ->whereRaw('LEFT(ti.NumInt, 4) = ?', [$agRef])
            ->whereNotNull('ti.CodeTech')
            ->orderBy('ti.CodeTech')
            ->get();

        if ($rows->isEmpty()) {
            return view('tourprep.index', [
                'date'=>$date, 'agRef'=>$agRef, 'mode'=>$mode, 'opt'=>$opt,
                'start'=>$startGeo, 'techTours'=>[],
            ]);
        }

        // Regroupement par technicien
        $perTech = [];
        foreach ($rows as $r) {
            $perTech[$r->CodeTech][] = $r;
        }

        $builder = new TourBuilder($geo, new DistanceServiceFast(), new DistanceServiceOsrm());
        $techTours = [];

        foreach ($perTech as $tech => $rdvs) {
            $data = $builder->build(
                ['label'=>$startLabel,'lat'=>$startGeo['lat'],'lon'=>$startGeo['lon']],
                collect($rdvs),
                $mode,
                $opt
            );

            $flagsByNum = collect($rdvs)->keyBy('NumInt')->map(function($r){
                return ['has_validated'=>(int)$r->has_validated, 'has_temp'=>(int)$r->has_temp];
            });

            $stops = array_map(function($s) use ($flagsByNum){
                $n = $s['numint'] ?? null;
                if ($n && isset($flagsByNum[$n])) { return $s + $flagsByNum[$n]; }
                return $s;
            }, $data['stops']);

            $techTours[] = [
                'tech'=>$tech,
                'stops'=>$stops,
                'segments'=>$data['segments'],
                'total'=>$data['total'],
                'geometry'=>$data['geometry'],
            ];
        }

        return view('tourprep.index', compact('date','agRef','mode','opt','techTours'))
            ->with('start', $startGeo);
    }
}
