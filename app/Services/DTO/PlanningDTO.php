<?php

namespace App\Services\DTO;

use Carbon\Carbon;

class PlanningDTO
{
    public string $codeTech;
    public Carbon $start; // Europe/Paris
    public Carbon $end;
    public string $numInt;
    public ?string $label = null;
    public ?string $commentaire = null;
    public ?string $cp = null;
    public ?string $ville = null;
    public bool $validated = false;

    public function __construct()
    {

    }
}
