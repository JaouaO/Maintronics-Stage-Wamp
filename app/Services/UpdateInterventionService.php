<?php

namespace App\Services;


use App\Services\DTO\PlanningDTO;
use App\Services\DTO\RdvTemporaireDTO;
use App\Services\DTO\UpdateInterventionDTO;
use App\Services\Write\PlanningWriteService;
use App\Services\Utils\ParisClockService;
use App\Services\Utils\VocabulaireService;
use App\Services\Write\HistoryWriteService;
use Illuminate\Support\Facades\DB;

class UpdateInterventionService
{
    private ParisClockService $clock;
    private VocabulaireService $vocabService;
    private PlanningWriteService $planningWriteService;
    private HistoryWriteService $historyWriteService;

    public function __construct(
        ParisClockService $clock,
        VocabulaireService  $vocabService,
        PlanningWriteService  $planningWriteService,
        HistoryWriteService  $historyWriteService

    ) {
        $this->vocabService = $vocabService;
        $this->clock = $clock;
        $this->planningWriteService = $planningWriteService;
        $this->historyWriteService = $historyWriteService;

    }

    /**
     * Orchestration minimale : construit bits/labels, applique la règle "pas dans le passé",
     * écrit t_intervention, t_planning_technicien (validé), historique et snapshot t_actions_etat.
     * NB: on reste pragmatique et utilise DB::table() directement ici pour ne pas multiplier les couches.
     */
    public function updateAndPlanRdv(UpdateInterventionDTO $dto): void
    {
        $vocab = $this->vocabService->load();
        $bitsTraitement  = $this->vocabService->bitsFromPosted($dto->traitement,  'TRAITEMENT',  $vocab);
        $bitsAffectation = $this->vocabService->bitsFromPosted($dto->affectation, 'AFFECTATION', $vocab);
        $labelsT = $this->vocabService->labelsFromBits('TRAITEMENT',  $bitsTraitement,  $vocab);
        $labelsA = $this->vocabService->labelsFromBits('AFFECTATION', $bitsAffectation, $vocab);
        $messageAffectation = $this->vocabService->textFromBits('AFFECTATION', $bitsAffectation, $vocab);


        $hasRdv = !empty($dto->date) && !empty($dto->heure);

        DB::transaction(function () use ($dto, $bitsTraitement, $bitsAffectation, $labelsT, $labelsA, $messageAffectation, $hasRdv) {
            // 1) t_intervention (upsert)
            $fields = [];
            if ($dto->marque) $fields['Marque'] = $dto->marque;
            if ($dto->cp)     $fields['CPLivCli'] = $dto->cp;
            if ($dto->ville)  $fields['VilleLivCli'] = $dto->ville;

            if ($dto->actionType == 'rdv_valide' && $hasRdv) {
                $start = $this->clock->parseLocal($dto->date, $dto->heure);

                $fields['DateIntPrevu']  = $dto->date;
                $fields['HeureIntPrevu'] = $dto->heure;
                $fields['CodeTech']      = (string) $dto->reaSal;
                $fields['DateValid']     = $this->clock->now()->toDateString();
                $fields['HeureValid']    = $this->clock->now()->format('H:i:s');

                // 2) planning validé
                $planningDTO = new PlanningDTO();
                $planningDTO->codeTech = (string) $dto->reaSal;
                $planningDTO->start    = $start;
                $planningDTO->end      = (clone $start)->addHour();
                $planningDTO->numInt   = $dto->numInt;
                $planningDTO->label    = trim($dto->numInt.' — '.mb_substr($dto->commentaire, 0, 60));
                $planningDTO->commentaire = $dto->commentaire;
                $planningDTO->cp       = $dto->cp;
                $planningDTO->ville    = $dto->ville;
                $planningDTO->validated = true;

                $this->planningWriteService->purgeValidatedByNumInt($dto->numInt);

                $this->planningWriteService->insertValidated($planningDTO, $dto->urgent);
            }

            DB::table('t_intervention')->updateOrInsert(['NumInt' => $dto->numInt], $fields);

            // 3) historique
            $meta = [
                'd'   => $dto->date ?: null,
                'h'   => $dto->heure ?: null,
                't'   => $dto->reaSal ?: null,
                'cp'  => $dto->cp ?: null,
                'v'   => $dto->ville ?: null,
                'lab' => mb_substr($dto->commentaire, 0, 60) ?: null,
                'tl'  => $labelsT ?: null,
                'al'  => $labelsA ?: null,
                'tb'  => $bitsTraitement ?: null,
                'ab'  => $bitsAffectation ?: null,
                'urg' => $dto->urgent ? 1 : null,
            ];

            if ($dto->actionType === 'appel' && $hasRdv) {
                $evtType = 'CALL_PLANNED';
                $evtMeta = $this->vocabService->pruneNulls($meta);
            } elseif ($dto->actionType === 'rdv_valide' && $hasRdv) {
                $evtType = 'RDV_FIXED';
                $evtMeta = $this->vocabService->pruneNulls($meta);
            }else{
                throw new \InvalidArgumentException('action_type invalide ou RDV incomplet.');
            }

            $this->historyWriteService->log($dto->numInt, $evtType, $evtMeta, $dto->objetTrait, $dto->commentaire, $dto->auteur);

            // 4) snapshot t_actions_etat
            $etat = [
                'bits_traitement'  => $bitsTraitement,
                'bits_affectation' => $bitsAffectation,
                'objet_traitement' => $messageAffectation,
                'contact_reel'     => $dto->contactReel,
                'urgent'           => $dto->urgent ? 1 : 0,
            ];
            if ($hasRdv && $dto->reaSal) {
                $start = $this->clock->parseLocal($dto->date, $dto->heure);
                $etat['rdv_prev_at']    = $start;
                $etat['tech_rdv_at']    = $start;
                if($dto->actionType ==='appel'){
                    $etat['reaffecte_code'] = $dto->reaSal;
                }elseif ($dto->actionType ==='rdv_valide'){
                    $etat['tech_code']      = $dto->reaSal;
                }
            }

            DB::table('t_actions_etat')->updateOrInsert(['NumInt' => $dto->numInt], $etat);
        });
    }

    public function ajoutRdvTemporaire(RdvTemporaireDTO $dto)
    {

        return DB::transaction(function () use ($dto) {
            $start = $this->clock->parseLocal($dto->date, $dto->heure);

            $planningDTO = new PlanningDTO();
            $planningDTO->codeTech = (string) $dto->reaSal;
            $planningDTO->start    = $start;
            $planningDTO->end      = (clone $start)->addHour();
            $planningDTO->numInt   = $dto->numInt;
            $planningDTO->label    = trim($dto->numInt.' — '.mb_substr($dto->commentaire, 0, 60));
            $planningDTO->commentaire = $dto->commentaire;
            $planningDTO->cp       = $dto->cp;
            $planningDTO->ville    = $dto->ville;
            $planningDTO->validated = false;

            $mode = $this->planningWriteService->upsertTemp($planningDTO);
            $evtType =  $mode === 'updated' ? 'RDV_TEMP_UPDATED' : 'RDV_TEMP_INSERTED';
            $evtMeta = $this->vocabService->pruneNulls([
                'd' => $start->toDateString(),
                'h' => $start->format('H:i'),
                't' => $dto->reaSal,
                'lab' => mb_substr($dto->commentaire, 0, 60) ?: null,
            ]);

            $this->historyWriteService->log($dto->numInt, $evtType, $evtMeta,'Planification', $dto->commentaire, $dto->auteur);

            return $mode;

        });


    }


}
