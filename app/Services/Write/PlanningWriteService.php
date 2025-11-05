<?php

namespace App\Services\Write;

use App\Services\DTO\PlanningDTO;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanningWriteService
{
    /**
     * Upsert d’un RDV temporaire (actif). Refuse si un validé actif existe déjà pour le dossier.
     * Garantit ensuite qu’il n’existe plus qu’UN temporaire actif pour le dossier (soft delete des autres).
     * @return 'updated'|'inserted'
     */
    public function upsertTemp(PlanningDTO $dto): string
    {


        // Existe-t-il déjà un TEMP actif au même slot pour ce tech ?
        $exists = DB::table('t_planning_technicien')
            ->where('NumIntRef', $dto->numInt)
            ->where('isObsolete', 0)
            ->where(function($w){ $w->whereNull('IsValidated')->orWhere('IsValidated', 0); })
            ->where('CodeTech',  $dto->codeTech)
            ->where('StartDate', $dto->start->toDateString())
            ->where('StartTime', $dto->start->format('H:i:s'))
            ->first(['id']);

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
            'isObsolete'  => 0,               // ⬅️ actif
        ];

        if (!empty($exists) && isset($exists->id)) {
            DB::table('t_planning_technicien')->where('id', $exists->id)->update($payload);
            $mode = 'updated';
        } else {
            DB::table('t_planning_technicien')->insert($payload);
            $mode = 'inserted';
        }

        // Soft-delete tous les AUTRES temporaires actifs du dossier (on garde celui qu’on vient d’upserter)
        DB::table('t_planning_technicien')
            ->where('NumIntRef', $dto->numInt)
            ->where('isObsolete', 0)
            ->where(function($w){ $w->whereNull('IsValidated')->orWhere('IsValidated', 0); })
            ->where(function($w) use ($dto) {
                $w->where('CodeTech', '!=', $dto->codeTech)
                    ->orWhere('StartDate', '!=', $dto->start->toDateString())
                    ->orWhere('StartTime', '!=', $dto->start->format('H:i:s'));
            })
            ->update(['isObsolete' => 1]);

        return $mode;
    }


    /** Insère un RDV VALIDÉ actif. (Les purges sont déclenchées par le service métier.) */
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
            'isObsolete'  => 0,               // ⬅️ actif
        ]);
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
            ->where('isObsolete', 0)
            ->where(function ($w) { $w->whereNull('IsValidated')->orWhere('IsValidated', 0); })
            ->update(['isObsolete' => 1]);
    }

    /** Soft-delete du TEMP actif exactement au créneau. */
    public function deleteTempBySlot(string $numInt, string $codeTech, CarbonInterface $start): int
    {
        return DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where('CodeTech',  $codeTech)
            ->where('StartDate', $start->toDateString())
            ->where('StartTime', $start->format('H:i:s'))
            ->where('isObsolete', 0)
            ->where(function ($w) { $w->whereNull('IsValidated')->orWhere('IsValidated', 0); })
            ->update(['isObsolete' => 1]);
    }

    /** Soft-delete de TOUS les VALIDÉS actifs d’un dossier (on garantit "zéro ou un"). */
    public function purgeValidatedByNumInt(string $numInt): int
    {
        return DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where('isObsolete', 0)
            ->where('IsValidated', 1)
            ->update(['isObsolete' => 1]);
    }

    /** Soft-delete de TOUS les TEMP actifs d’un dossier (optionnellement en gardant un slot donné). */
    public function purgeTempsByNumInt(
        string $numInt,
        ?string $excludeDate = null,
        ?string $excludeTime = null,
        ?string $excludeTech = null
    ): int {
        $q = DB::table('t_planning_technicien')
            ->where('NumIntRef', $numInt)
            ->where('isObsolete', 0)
            ->where(function ($w) { $w->whereNull('IsValidated')->orWhere('IsValidated', 0); });

        if ($excludeDate && $excludeTime && $excludeTech) {
            $q->where(function ($w) use ($excludeDate, $excludeTime, $excludeTech) {
                $w->where('StartDate', '!=', $excludeDate)
                    ->orWhere('StartTime', '!=', $excludeTime)
                    ->orWhere('CodeTech',  '!=', $excludeTech);
            });
        }

        return $q->update(['isObsolete' => 1]);
    }


}


