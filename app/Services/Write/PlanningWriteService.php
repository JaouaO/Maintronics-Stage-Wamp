<?php

namespace App\Services\Write;

use App\Services\DTO\PlanningDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanningWriteService
{
    public function upsertTemp(PlanningDTO $dto): string
    {
        $exists = DB::table('t_planning_technicien')->where([
            ['NumIntRef', '=', $dto->numInt],
            ['StartDate', '=', $dto->start->toDateString()],
            ['StartTime', '=', $dto->start->format('H:i:s')],
        ])->first();

        $payload = [
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
            'IsValidated' => 0,
        ];

        if ($exists) {
            DB::table('t_planning_technicien')->where('id', $exists->id)->update($payload);
            return 'updated';
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
}


