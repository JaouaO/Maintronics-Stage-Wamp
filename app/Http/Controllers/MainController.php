<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotBlankRequest;
use App\Http\Requests\RdvTemporaireRequest;
use App\Http\Requests\ReplanifierRequest;
use App\Http\Requests\ShowInterventionsRequest;
use App\Http\Requests\StoreInterventionRequest;
use App\Http\Requests\SuggestNumIntRequest;
use App\Http\Requests\UpdateInterventionRequest;
use App\Services\AccessInterventionService;
use App\Services\AuthService;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

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

    /**
     * Injection des services métier utilisés dans ce contrôleur.
     */
    public function __construct(
        AuthService                $authService,
        InterventionService        $interventionService,
        TraitementDossierService   $traitementDossierService,
        PlanningService            $planningService,
        PlanningWriteService       $planningWriteService,
        UpdateInterventionService  $updateInterventionService,
        AccessInterventionService  $accessInterventionService,
        InterventionHistoryService $historyService,
        ParisClockService          $clockService
    ) {
        $this->authService              = $authService;
        $this->interventionService      = $interventionService;
        $this->traitementDossierService = $traitementDossierService;
        $this->planningService          = $planningService;
        $this->planningWriteService     = $planningWriteService;
        $this->updateInterventionService = $updateInterventionService;
        $this->accessInterventionService = $accessInterventionService;
        $this->historyService           = $historyService;
        $this->clockService             = $clockService;
    }

    /**
     * Affiche le formulaire de login.
     * Redirige vers la liste des interventions si déjà connecté.
     */
    public function showLoginForm()
    {
        if (session()->has('id')) {
            return redirect()->route('interventions.show', ['id' => session('id')]);
        }

        return view('login');
    }

    /**
     * Traite le login technicien (codeSal).
     * - Vérifie le compte
     * - Vérifie les horaires
     * - Log l'accès si OK
     */
    public function login(NotBlankRequest $request)
    {
        $codeSal = $request->input('codeSal');
        $login   = $this->authService->login($codeSal);

        if (!$login['success']) {
            return redirect()->route('erreur')->with('message', $login['message']);
        }

        $ip = $request->ip();
        $id = $this->authService->generateId();

        $result = $this->authService->verifHoraires($codeSal);

        $request->session()->put([
            'id'        => $id,
            'codeSal'   => $login['user']['codeSal'],
            'CodeAgSal' => $login['user']['CodeAgSal'],
        ]);

        if ($result['success']) {
            $this->authService->logAccess($id, $ip, $codeSal, $login['user']['CodeAgSal']);
            return redirect()->route('interventions.show', ['id' => session('id')]);
        }

        return redirect()->route('erreur')->with('message', $result['message']);
    }

    /**
     * Liste paginée des interventions (page tableau).
     * Gère agence active / agences autorisées / scope / lieu / recherche.
     */
    public function showInterventions(ShowInterventionsRequest $request)
    {
        $v       = $request->validated();
        $perPage = (int)($v['per_page'] ?? 10);
        $q       = $v['q'] ?? null;
        $scope   = $v['scope'] ?? null;
        $lieu    = $v['lieu'] ?? 'all';

        $agencesAutorisees = array_values((array)session('agences_autorisees', []));
        $codeSal           = (string)session('codeSal', '');
        $defaultAgence     = (string)session('defaultAgence', '');

        $hasMany = count($agencesAutorisees) > 1;
        $agParam = $request->query('ag'); // null si absent

        // Règle de forçage : plusieurs agences + pas de défaut valide + pas de ?ag= → "Toutes"
        $forceAllAgences = $hasMany
            && (empty($defaultAgence) || !in_array($defaultAgence, $agencesAutorisees, true))
            && ($agParam === null || $agParam === '');

        // Résolution agence active
        $selectedAg = null;
        if (is_string($agParam) && $agParam !== '' && in_array($agParam, $agencesAutorisees, true)) {
            $selectedAg = $agParam;
        } elseif ($agParam === '_ALL' || $forceAllAgences) {
            $selectedAg = null; // "Toutes"
        } else {
            if ($defaultAgence && in_array($defaultAgence, $agencesAutorisees, true)) {
                $selectedAg = $defaultAgence;
            } elseif (!$hasMany && !empty($agencesAutorisees)) {
                $selectedAg = $agencesAutorisees[0];
            } else {
                $selectedAg = null; // fallback "Toutes"
            }
        }

        $rows = $this->interventionService->listPaginatedSimple(
            $perPage,
            $agencesAutorisees,
            $codeSal,
            $q,
            $scope,
            $selectedAg,
            $lieu
        );

        return view('interventions.show', [
            'rows'               => $rows,
            'perPage'            => $perPage,
            'q'                  => $q,
            'scope'              => $scope,
            'agencesAutorisees'  => $agencesAutorisees,
            'ag'                 => $selectedAg,      // null = "Toutes"
            'forceAllAgences'    => $forceAllAgences,
            'lieu'               => $lieu,
        ]);
    }

    /**
     * Popup historique d'une intervention.
     * Contrôle d'accès par agence via préfixe NumInt.
     */
    public function history(Request $request, string $numInt): \Illuminate\Http\Response
    {
        $agencesAutorisees = array_map('strtoupper', (array)$request->session()->get('agences_autorisees', []));
        $agFromNum         = $this->accessInterventionService->agenceFromNumInt($numInt);

        if ($agFromNum === '' || !in_array($agFromNum, $agencesAutorisees, true)) {
            abort(403, 'Vous n’avez pas accès à cette intervention.');
        }

        $suivis = $this->historyService->fetchHistory($numInt);

        if (View::exists('interventions.history_popup')) {
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

    /**
     * Page d'édition d'une intervention (fiche + planning + historique).
     */
    public function editIntervention(string $numInt)
    {
        $payload = $this->traitementDossierService->loadEditPayload($numInt);

        if (!$payload['interv']) {
            return redirect()
                ->route('interventions.show', ['id' => session('id')])
                ->with('error', 'Intervention introuvable.');
        }

        // Liste unifiée des personnes (affectation + agenda) pour ce dossier
        $people       = $this->accessInterventionService->listPeopleForNumInt($numInt);
        $agendaPeople = $this->accessInterventionService->listAgendaPeopleForNumInt($numInt);

        return view('interventions.edit', $payload + [
                'agendaPeople' => $agendaPeople, // {CodeSal, NomSal, access_level, is_tech, has_rdv}
                'people'       => $people,       // Collection {CodeSal, NomSal, CodeAgSal, access_level}
            ]);
    }

    /**
     * API JSON : planning d'un technicien sur une période.
     * Filtrage possible par "allowed" si numInt fourni.
     */
    public function apiPlanningTech(Request $request, string $codeTech): \Illuminate\Http\JsonResponse
    {
        try {
            $numInt  = (string)$request->query('numInt', '');
            $allowed = [];

            if ($numInt !== '') {
                $allowed = $this->accessInterventionService
                    ->listAgendaPeopleForNumInt($numInt)
                    ->pluck('CodeSal')
                    ->map(fn($c) => strtoupper(trim((string)$c)))
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

    /**
     * Validation / mise à jour globale depuis le formulaire principal.
     * Délègue toute la logique métier à UpdateInterventionService.
     */
    public function updateIntervention(UpdateInterventionRequest $request, string $numInt): \Illuminate\Http\RedirectResponse
    {
        $dto = UpdateInterventionDTO::fromRequest($request, (string)$numInt);

        try {
            $this->updateInterventionService->updateAndPlanRdv($dto);

            return redirect()
                ->route('interventions.edit', $dto->numInt)
                ->with('ok', 'Mise à jour effectuée.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['global' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['global' => 'Une erreur est survenue.'])->withInput();
        }
    }

    /**
     * Ajout / remplacement d’un RDV temporaire (appelé en AJAX).
     * - 200 JSON ok:true si le RDV est créé ou remplacé
     * - 409 VALIDATED_EXISTS si un RDV validé existe déjà
     * - 422 erreurs de validation
     */
    public function rdvTemporaire(RdvTemporaireRequest $request, string $numInt): \Illuminate\Http\JsonResponse
    {
        try {
            $codeSalAuteur = (string)(session('codeSal') ?: 'system');
            $dto           = RdvTemporaireDTO::fromValidated($request->validated(), $numInt, $codeSalAuteur);

            $mode = $this->updateInterventionService->ajoutRdvTemporaire($dto);

            return response()->json(['ok' => true, 'mode' => $mode]);

        } catch (\RuntimeException $re) {
            if ($re->getMessage() === 'VALIDATED_EXISTS') {
                return response()->json([
                    'ok'   => false,
                    'code' => 'VALIDATED_EXISTS',
                    'msg'  => 'Un RDV validé actif existe déjà pour ce dossier. Confirmez la suppression pour poursuivre.',
                ], 409);
            }

            throw $re;

        } catch (ValidationException $ve) {
            return response()->json(['ok' => false, 'errors' => $ve->errors()], 422);

        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'    => false,
                'type'  => 'Throwable',
                'errmsg'=> $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Suppression d’un RDV temporaire par id (AJAX).
     */
    public function rdvTempDelete(Request $request, string $numInt, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $deleted = $this->planningWriteService->deleteTempById($numInt, $id);

            if ($deleted === 0) {
                return response()->json([
                    'ok'  => false,
                    'msg' => "Aucun RDV temporaire trouvé (ou déjà validé) pour ce dossier.",
                ], 404);
            }

            return response()->json(['ok' => true, 'deleted' => 1]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * Formulaire de création d’une nouvelle intervention.
     * - Pré-sélectionne l’agence
     * - Propose un NumInt
     * - Pré-remplit date/heure avec maintenant.
     */
    public function createIntervention(Request $request)
    {
        $agencesAutorisees = (array)$request->session()->get('agences_autorisees', []);
        $defaultAgence     = (string)$request->session()->get('defaultAgence', '');

        if (empty($agencesAutorisees)) {
            return redirect()->route('interventions.show')
                ->with('error', 'Aucune agence autorisée pour ce compte.');
        }

        $now = $this->clockService->now();

        $agence = (string)(
        $request->query('agence')
            ?: ($defaultAgence !== '' ? $defaultAgence : (reset($agencesAutorisees) ?: ''))
        );

        try {
            $suggest = $this->interventionService->nextNumInt($agence, $now);
        } catch (\Throwable $e) {
            $suggest = '';
        }

        return view('interventions.create', [
            'agences' => $agencesAutorisees,
            'agence'  => $agence,
            'suggest' => $suggest,
            'defaults'=> [
                'DateIntPrevu'  => $now->toDateString(),
                'HeureIntPrevu' => $now->format('H:i'),
                'VilleLivCli'   => '',
                'CPLivCli'      => '',
                'Marque'        => '',
                'Commentaire'   => '',
                'Urgent'        => false,
            ],
            'codeSal' => (string)$request->session()->get('codeSal', ''),
        ]);
    }

    /**
     * API AJAX pour suggérer un nouveau NumInt à partir d’une agence + date.
     * Fallback local si la méthode métier jette une exception.
     */
    public function suggestNumInt(SuggestNumIntRequest $request)
    {
        $ag    = strtoupper(trim($request->validated()['agence']));
        $dateS = (string)$request->query('date', '');

        $ref = $dateS
            ? $this->clockService->parseLocal($dateS, '00:00')
            : $this->clockService->now();

        try {
            $num = $this->interventionService->nextNumInt($ag, $ref);

            return response()->json(['ok' => true, 'numInt' => $num]);
        } catch (\Throwable $e) {
            // Fallback local : AGENCE-YYMM-##### (5 chiffres, reset chaque mois)
            $yymm = $ref->format('ym');
            $max  = DB::table('t_intervention')
                ->where('NumInt', 'like', $ag . '-' . $yymm . '-%')
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(NumInt, '-', -1) AS UNSIGNED)) as m")
                ->value('m');

            $next = (int)$max + 1;
            $num  = sprintf('%s-%s-%05d', $ag, $yymm, $next ?: 1);

            return response()->json(['ok' => true, 'numInt' => $num]);
        }
    }

    /**
     * Création d’une intervention “minimale” à partir du formulaire “nouvelle intervention”.
     * Écrit dans t_intervention + t_actions_etat via UpdateInterventionService.
     */
    public function storeIntervention(StoreInterventionRequest $request): \Illuminate\Http\RedirectResponse
    {
        $p = $request->validated();

        // Date de référence pour YYMM du NumInt (si champ vide)
        $dateRef = !empty($p['DateIntPrevu'])
            ? $this->clockService->parseLocal($p['DateIntPrevu'], '00:00')
            : $this->clockService->now();

        // NumInt : requis, mais fallback si vide
        $numInt = !empty($p['NumInt'])
            ? $p['NumInt']
            : $this->interventionService->nextNumInt($p['Agence'], $dateRef);

        $codeSal = (string)$request->session()->get('codeSal', '');

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
            null // reaffecte_code éventuel (non utilisé ici)
        );

        return redirect()
            ->route('interventions.edit', $numInt)
            ->with('ok', 'Intervention créée.');
    }

    /**
     * Replanification “fine” en AJAX (modale tournée).
     * Ne met à jour que les champs transmis par la ReplanifierRequest.
     */
    public function replanifierAjax(ReplanifierRequest $request, string $numInt): \Illuminate\Http\JsonResponse
    {
        // Garde d’accès par agence (même logique que history())
        $agencesAutorisees = array_map('strtoupper', (array)$request->session()->get('agences_autorisees', []));
        $agFromNum         = $this->accessInterventionService->agenceFromNumInt($numInt);

        if ($agFromNum === '' || !in_array($agFromNum, $agencesAutorisees, true)) {
            return response()->json(['ok' => false, 'message' => 'Accès refusé à ce dossier.'], 403);
        }

        try {
            // Payload partiel (seuls les champs renseignés sont présents)
            $payload = $request->toUpdatePayload();
            if (empty($payload)) {
                return response()->json(['ok' => false, 'message' => 'Aucun champ à mettre à jour.'], 422);
            }

            // Compatibilité avec le DTO existant (même format que le gros formulaire)
            $compatRequest = new Request($payload);
            $dto           = UpdateInterventionDTO::fromRequest($compatRequest, (string)$numInt);

            DB::transaction(function () use ($dto) {
                $this->updateInterventionService->updateAndPlanRdv($dto);
            });

            return response()->json(['ok' => true]);
        } catch (ValidationException $ve) {
            return response()->json(['ok' => false, 'errors' => $ve->errors()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }
}
