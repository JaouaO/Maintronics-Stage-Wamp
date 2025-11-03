<?php

namespace App\Services\Write;

use App\Services\DTO\PlanningDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanningWriteService
{
    // PlanningWriteService.php
    public function upsertTemp(PlanningDTO $dto): string
    {
        $exists = DB::table('t_planning_technicien')
            ->where('NumIntRef', $dto->numInt)
            ->where('StartDate', $dto->start->toDateString())
            ->where('StartTime', $dto->start->format('H:i:s'))
            ->where('CodeTech',  $dto->codeTech) // ← ajoutez le technicien
            ->where(function($w){                 // ← ne viser QUE les temporaires
                $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
            })
            ->first();

        $payload = [
            'CodeTech'    => $dto->codeTech,
            'StartDate'   => $dto->start->toDateString(),
            'StartTime'   => $dto->start->format('H:i:s'),
            'EndDate'     => $dto->end->toDateString(),
            'EndTime'     => $dto->end->format('H:i:s'),
            'NumIntRef'   => $dto->numInt,
            'Label'       => (string)$dto->label,
            'Commentaire' => (string)$dto->commentaire,
            'CPLivCli'    => $dto->cp,
            'VilleLivCli' => $dto->ville,
            'IsValidated' => 0,
        ];

        if ($exists) {
            DB::table('t_planning_technicien')
                ->where('id', $exists->id) // on ne touchera jamais un validé
                ->update($payload);
            return 'updated';
        }

        // (option protection) : si un validé existe exactement au même slot, on refuse la création du temp
        $validatedExists = DB::table('t_planning_technicien')
            ->where('NumIntRef', $dto->numInt)
            ->where('StartDate', $dto->start->toDateString())
            ->where('StartTime', $dto->start->format('H:i:s'))
            ->where('IsValidated', 1)
            ->exists();

        if ($validatedExists) {
            // laissez remonter l’info : l’API rdv_temporaire renverra une erreur lisible au front
            throw new \InvalidArgumentException('Un RDV validé existe déjà à ce créneau.');
        }

        DB::table('t_planning_technicien')->insert($payload);
        return 'inserted';
    }


    public function insertValidated(PlanningDTO $dto, bool $urgent): void
    {
        DB::table('t_planning_technicien')->insert([
            'CodeTech'    => $dto->codeTech,
            'StartDate'   => $dto->start->toDateString(),
            'StartTime'   => $dto->start->format('H:i:s'),
            'EndDate'     => $dto->end->toDateString(),
            'EndTime'     => $dto->end->format('H:i:s'),
            'NumIntRef'   => $dto->numInt,
            'Label'       => (string) $dto->label,
            'Commentaire' => (string) $dto->commentaire,
            'CPLivCli'    => $dto->cp,
            'VilleLivCli' => $dto->ville,
            'IsValidated' => 1,
            'IsUrgent'    => $urgent ? 1 : 0,
        ]);
    }


    public function purgeTempsByNumInt(string $numInt): int
    {
        return DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where(function ($w) {
                $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
            })
            ->delete();
    }

    public function purgeValidatedByNumInt(string $numInt): int
    {
        return DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where(function ($w) {
                $w->Where('IsValidated', 1);
            })
            ->delete();

    }

    /**
     * Supprime un seul RDV temporaire (non validé) par son id pour un dossier donné.
     * @return int nombre de lignes supprimées (0 ou 1)
     */
    public function deleteTempById(string $numInt, int $id): int
    {
        return DB::table('t_planning_technicien')
            ->where('id', $id)
            ->where('NumIntRef', $numInt)
            ->where(function ($w) {
                $w->whereNull('IsValidated')->orWhere('IsValidated', 0);
            })
            ->delete();
    }

    // PlanningWriteService.php
    public function deleteTempBySlot(string $numInt, string $codeTech, \Carbon\Carbon $start): int
    {
        return DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where('CodeTech',  $codeTech)
            ->where('StartDate', $start->toDateString())
            ->where('StartTime', $start->format('H:i:s'))
            ->where(function($w){ $w->whereNull('IsValidated')->orWhere('IsValidated', 0); })
            ->delete();
    }

}


