<?php
// app/Http/Controllers/TourPrepController.php

namespace App\Http\Controllers;

use App\Services\TourPrep\AutoplanService;
use App\Services\UpdateInterventionService;
use Carbon\Carbon;
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

    public function autoplanGenerate(Request $req)
    {
        try {
            $date  = $req->input('date');              // YYYY-MM-DD
            $agRef = $req->input('agref');             // ex: M59L
            $mode  = $req->input('mode', 'fast');      // fast|precise
            $opt   = (bool)$req->input('opt', 1);      // on optimise côté aperçu

            if (!$date || !$agRef) {
                return response()->json(['ok'=>false,'message'=>'Paramètres manquants (date, agref).'], 422);
            }

            /** @var AutoplanService $svc */
            $svc = app(AutoplanService::class);
            $proposal = $svc->generateProposal($agRef, $date, $mode, $opt);

            return response()->json(['ok'=>true] + $proposal);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok'=>false,'message'=>$e->getMessage()], 500);
        }
    }

    public function autoplanCommit(Request $req): \Illuminate\Http\JsonResponse
    {
        $agRef = (string)$req->input('agref', '');
        $date  = (string)$req->input('date', '');
        $ops   = (array)$req->input('assignments', []); // [{numInt, fromTech, toTech, rdv_at}...]

        if (!$agRef || !$date || empty($ops)) {
            return response()->json(['ok' => false, 'message' => 'Entrées incomplètes (agref, date, assignments).'], 422);
        }

        try {
            DB::transaction(function () use ($ops) {
                /** @var UpdateInterventionService $svc */
                $svc = app(UpdateInterventionService::class);

                foreach ($ops as $op) {
                    $num  = (string)($op['numInt']  ?? '');
                    $to   = (string)($op['toTech']  ?? '');
                    $when = (string)($op['rdv_at']  ?? ''); // "YYYY-MM-DD HH:MM:SS"

                    if ($num === '' || $to === '' || $when === '') {
                        continue;
                    }

                    // Construire le DTO attendu par UpdateInterventionService::updateAndPlanRdv()
                    $dt     = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $when, 'Europe/Paris');
                    $compat = new \Illuminate\Http\Request([
                        'code_sal_auteur' => (string)(session('codeSal') ?: 'system'),
                        'rea_sal'         => $to,
                        'date_rdv'        => $dt->toDateString(),
                        'heure_rdv'       => $dt->format('H:i'),
                        'urgent'          => false,
                        'commentaire'     => '(autoplanning)',
                        'contact_reel'    => '',
                        'objet_trait'     => 'Planification',
                        'traitement'      => [],
                        'affectation'     => [],
                        'code_postal'     => null,
                        'ville'           => null,
                        'marque'          => null,
                        'action_type'     => 'rdv_valide', // ← très important
                    ]);

                    $dto = \App\Services\DTO\UpdateInterventionDTO::fromRequest($compat, $num);
                    $svc->updateAndPlanRdv($dto);
                }
            });

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Commit annulé : ' . $e->getMessage()], 500);
        }
    }


}
