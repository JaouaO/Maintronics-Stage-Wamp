<?php

namespace App\Services\Utils;

use Carbon\Carbon;

class ParisClockService
{
    public function now(): Carbon
    {
        return Carbon::now('Europe/Paris');
    }

    public function parseLocal(string $ymd, string $hm): Carbon
    {
        return Carbon::parse("$ymd $hm", 'Europe/Paris');
    }
}
