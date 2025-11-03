<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\ShowInterventionsRequest;
use App\Http\Requests\StoreInterventionRequest;
use App\Http\Requests\SuggestNumIntRequest;
use App\Http\Requests\UpdateInterventionRequest;
use App\Services\AccessInterventionService;
use App\Services\DTO\RdvTemporaireDTO;
use App\Services\DTO\UpdateInterventionDTO;
use App\Services\InterventionHistoryService;
use App\Services\InterventionService;
use App\Services\PlanningService;
use App\Services\TraitementDossierService;
use App\Services\UpdateInterventionService;
use App\Services\Utils\ParisClockService;
use App\Services\Write\PlanningWriteService;
use Illuminate\Http\Request;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class MainController extends Controller
{
    protected AuthService $authService;
    protected InterventionService $interventionService;
    private TraitementDossierService $traitementDossierService;
    private PlanningService $planningService;
    private PlanningWriteService $planningWriteService;

    private UpdateInterventionService $updateInterventionService;
    private AccessInterventionService $accessInterventionService;
    private InterventionHistoryService $historyService;
    private ParisClockService $clockService;

    public function __construct(
        AuthService               $authService,
        InterventionService       $interventionService,
        TraitementDossierService  $traitementDossierService,
        PlanningService           $planningService,
        PlanningWriteService      $planningWriteService,
        UpdateInterventionService $updateInterventionService,
        AccessInterventionService $accessInterventionService,
        InterventionHistoryService $historyService,
        ParisClockService $clockService
    )
    {
        $this->authService = $authService;
        $this->interventionService = $interventionService;
        $this->traitementDossierService = $traitementDossierService;
        $this->planningService = $planningService;
        $this->planningWriteService = $planningWriteService;
        $this->updateInterventionService = $updateInterventionService;
        $this->accessInterventionService = $accessInterventionService;
        $this->historyService = $historyService;
        $this->clockService = $clockService;
    }

    public function showLoginForm()
    {
        if (session()->has('id')) {
            return redirect()->route('interventions.show', ['id' => session('id')]);        }
        return view('login');
    }

    public function login(NotBlankRequest $request)
    {
        $codeSal = $request->input('codeSal');
        $login = $this->authService->login($codeSal);

        if (!$login['success']) {
            return redirect()->route('erreur')->with('message', $login['message']);
        }

        $ip = $request->ip();
        $id = $this->authService->generateId();

        $result = $this->authService->verifHoraires($codeSal);

        $request->session()->put([
            'id' => $id,
            'codeSal' => $login['user']['codeSal'],
            'CodeAgSal' => $login['user']['CodeAgSal'],
        ]);

        if ($result['success']) {
            $this->authService->logAccess($id, $ip, $codeSal, $login['user']['CodeAgSal']);
            return redirect()->route('interventions.show', ['id' => session('id')]);
        }

        return redirect()->route('erreur')->with('message', $result['message']);
    }

    public function showInterventions(ShowInterventionsRequest $request)
    {
        $v = $request->validated();
        $perPage = (int)($v['per_page'] ?? 10);
        $q       = $v['q'] ?? null;
        $scope   = $v['scope'] ?? null;

        $agencesAutorisees = (array) session('agences_autorisees', []);
        $codeSal           = (string) session('codeSal', '');

        $rows = $this->interventionService
            ->listPaginatedSimple($perPage, $agencesAutorisees, $codeSal, $q, $scope);

        return view('interventions.show', compact('rows','perPage','q','scope'));
    }


    // GET /interventions/{numInt}/history
    public function history(Request $request, string $numInt): \Illuminate\Http\Response
    {
        // Garde d’accès par agence (préfixe du NumInt)
        $agencesAutorisees = array_map('strtoupper', (array) $request->session()->get('agences_autorisees', []));
        $agFromNum         = $this->accessInterventionService->agenceFromNumInt($numInt);

        if ($agFromNum === '' || !in_array($agFromNum, $agencesAutorisees, true)) {
            abort(403, 'Vous n’avez pas accès à cette intervention.');
        }

        $suivis = $this->historyService->fetchHistory($numInt);

        if (\Illuminate\Support\Facades\View::exists('interventions.history_popup')) {
            return response()->view('interventions.history_popup', [
                'numInt' => $numInt,
                'suivis' => $suivis,
            ]);
        }

        return response()->view('interventions.history_fallback', [
            'numInt' => $numInt,
            'suivis' => $suivis,
        ]);
    }



    public function editIntervention($numInt)
    {
        $payload = $this->traitementDossierService->loadEditPayload($numInt);
        if (!$payload['interv']) {
            return redirect()
                ->route('interventions.show', ['id' => session('id')])
                ->with('error', 'Intervention introuvable.');
        }

        // ↓ Liste unifiée des personnes sélectionnables pour ce dossier
        $people = $this->accessInterventionService->listPeopleForNumInt($numInt);
        $agendaPeople = $this->accessInterventionService->listAgendaPeopleForNumInt($numInt);

        // vous pouvez passer $people à vos partials au lieu de techniciens/salaries
        return view('interventions.edit', $payload + [
                'agendaPeople' => $agendaPeople, // {CodeSal, NomSal, access_level, is_tech, has_rdv}
                'people' => $people, // Collection de {CodeSal, NomSal, CodeAgSal, access_level}
            ]);
    }

    /** API planning */
    // MainController.php — apiPlanningTech()

    public function apiPlanningTech(Request $request, $codeTech): \Illuminate\Http\JsonResponse
    {
        try {
            $numInt = (string) $request->query('numInt', '');
            $allowed = [];

            if ($numInt !== '') {
                $allowed = $this->accessInterventionService
                    ->listAgendaPeopleForNumInt($numInt)
                    ->pluck('CodeSal')
                    ->map(fn($c) => strtoupper(trim((string)$c))) // normalisation
                    ->unique()
                    ->values()
                    ->all();
            }

            $payload = $this->planningService->getPlanning(
                $codeTech,
                $request->query('from'),
                $request->query('to'),
                (int)$request->query('days', 5),
                'Europe/Paris',
                $allowed
            );
            return response()->json($payload);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'msg' => 'Erreur SQL lors de la lecture du planning'], 500);
        }
    }





    public function updateIntervention(UpdateInterventionRequest $request, $numInt): \Illuminate\Http\RedirectResponse
    {

        Log::debug('urgent?', ['raw' => $request->input('urgent'), 'bool' => $request->boolean('urgent')]);
        $dto = UpdateInterventionDTO::fromRequest($request, (string)$numInt);

        try {
            $this->updateInterventionService->updateAndPlanRdv($dto);
            return redirect()->route('interventions.edit', $dto->numInt)->with('ok', 'Mise à jour effectuée.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['global' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['global' => 'Une erreur est survenue.'])->withInput();
        }
    }

    public function rdvTemporaire(Request $request, string $numInt): \Illuminate\Http\JsonResponse
    {

        try {
            $codeSalAuteur = session('codeSal') ?: null;
            $dto = RdvTemporaireDTO::fromRequest($request, $numInt, $codeSalAuteur);

            $mode = $this->updateInterventionService->ajoutRdvTemporaire($dto);

            return response()->json(['ok' => true, 'mode' => $mode]);

        } catch (\Illuminate\Database\QueryException $qe) {
            $ei = $qe->errorInfo ?? [];
            return response()->json([
                'ok' => false,
                'type' => 'QueryException',
                'sqlstate' => $ei[0] ?? null,
                'errno' => $ei[1] ?? null,
                'errmsg' => $ei[2] ?? $qe->getMessage(),
                'file' => basename($qe->getFile()) . ':' . $qe->getLine(),
            ], 500);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['ok' => false, 'errors' => $ve->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'type' => 'Throwable',
                'errmsg' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }


    public function rdvTempCheck(Request $request, string $numInt): \Illuminate\Http\JsonResponse
    {
        try {
            $exclude = $request->input('exclude');
            $rows = $this->planningService->listTempsByNumInt($numInt);

            if (is_array($exclude)
                && !empty($exclude['codeTech'])
                && !empty($exclude['startDate'])
                && !empty($exclude['startTime'])) {

                $rows = $rows->reject(function ($r) use ($exclude) {
                    return ($r->CodeTech === $exclude['codeTech'])
                        && ($r->StartDate === $exclude['startDate'])
                        && ($r->StartTime === $exclude['startTime']);
                });
            }
            return response()->json([
                'ok' => true,
                'count' => $rows->count(),
                'items' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    public function rdvTempPurge(Request $request, string $numInt): \Illuminate\Http\JsonResponse
    {
        try {
            $deleted = $this->planningWriteService->purgeTempsByNumInt($numInt);
            return response()->json(['ok' => true, 'deleted' => $deleted]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    public function rdvTempDelete(Request $request, string $numInt, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $deleted = $this->planningWriteService->deleteTempById($numInt, $id);

            if ($deleted === 0) {
                return response()->json([
                    'ok' => false,
                    'msg' => "Aucun RDV temporaire trouvé (ou déjà validé) pour ce dossier.",
                ], 404);
            }

            return response()->json(['ok' => true, 'deleted' => 1]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }
// MainController.php (ajoute/replace seulement ces 2 méthodes)

    public function createIntervention(Request $request)
    {
        $agencesAutorisees = (array) $request->session()->get('agences_autorisees', []);
        $defaultAgence     = (string) $request->session()->get('defaultAgence', '');

        if (empty($agencesAutorisees)) {
            return redirect()->route('interventions.show')
                ->with('error', 'Aucune agence autorisée pour ce compte.');
        }

        $now    = $this->clockService->now(); // ← au lieu de Carbon::now()
        $agence = (string) (
        $request->query('agence')
            ?: ($defaultAgence !== '' ? $defaultAgence : (reset($agencesAutorisees) ?: ''))
        );        try {
            $suggest = $this->interventionService->nextNumInt($agence, $now); // $now est un Carbon
        } catch (\Throwable $e) {
            $suggest = '';
        }

        return view('interventions.create', [
            'agences'  => $agencesAutorisees,
            'agence'   => $agence,
            'suggest'  => $suggest,
            'defaults' => [
                'DateIntPrevu'  => $now->toDateString(),
                'HeureIntPrevu' => $now->format('H:i'),
                'VilleLivCli'   => '',
                'CPLivCli'      => '',
                'Marque'        => '',
                'Commentaire'   => '',
                'Urgent'        => false,
            ],
            'codeSal' => (string) $request->session()->get('codeSal', ''),
        ]);
    }

    public function suggestNumInt(SuggestNumIntRequest $request)
    {
        $ag    = strtoupper(trim($request->validated()['agence']));
        $dateS = (string) $request->query('date', '');
        $ref   = $dateS ? $this->clockService->parseLocal($dateS, '00:00')
            : $this->clockService->now();

        try {
            // ✅ même règle que partout ailleurs
            $num = $this->interventionService->nextNumInt($ag, $ref);
            return response()->json(['ok' => true, 'numInt' => $num]);
        } catch (\Throwable $e) {
            // 🔁 Fallback local : AGENCE-YYMM-##### (5 chiffres, reset chaque mois)
            $yymm = $ref->format('ym'); // ex: 2025-10 -> "2510"
            $max  = DB::table('t_intervention')
                ->where('NumInt', 'like', $ag.'-'.$yymm.'-%')
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(NumInt, '-', -1) AS UNSIGNED)) as m")
                ->value('m');

            $next = (int)$max + 1;
            $num  = sprintf('%s-%s-%05d', $ag, $yymm, $next ?: 1);
            return response()->json(['ok' => true, 'numInt' => $num]);
        }
    }

    public function storeIntervention(StoreInterventionRequest $request): \Illuminate\Http\RedirectResponse
    {
        // Données déjà nettoyées + validées par la FormRequest
        $p = $request->validated();

        // Date de référence pour le YYMM du NumInt (si jamais vide en entrée — garde-fou)
        $dateRef = !empty($p['DateIntPrevu'])
            ? $this->clockService->parseLocal($p['DateIntPrevu'], '00:00')
            : $this->clockService->now();

        // NumInt : en principe requis par la FormRequest ; fallback si champ finalement vide
        $numInt = !empty($p['NumInt'])
            ? $p['NumInt']
            : $this->interventionService->nextNumInt($p['Agence'], $dateRef);

        $codeSal = (string) $request->session()->get('codeSal', '');

        // IMPORTANT : createMinimal doit écrire :
        // - t_intervention : NumInt, Marque, VilleLivCli, CPLivCli (uniquement colonnes existantes)
        // - t_actions_etat : urgent, rdv_prev_at (composé de DateIntPrevu+HeureIntPrevu), commentaire, reaffecte_code…
        $this->updateInterventionService->createMinimal(
            $numInt,
            $p['Marque']        ?? null,
            $p['VilleLivCli']   ?? null,
            $p['CPLivCli']      ?? null,
            $p['DateIntPrevu']  ?? null,
            $p['HeureIntPrevu'] ?? null,
            $p['Commentaire']   ?? null,
            $codeSal ?: 'system',
            ((string)($p['Urgent'] ?? '0') === '1'),
            null
        );

        return redirect()
            ->route('interventions.edit', $numInt)
            ->with('ok', 'Intervention créée.');
    }

//return redirect('/ClientInfo?id=' . session('user')->idUser . '&action=dossier-detail&numInt=' . $numInt);
}

