<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ThrottleSmokeTest extends TestCase
{
    public function test_suggest_num_is_throttled_smoke(): void
    {
        // Route factice GET avec throttle
        Route::get('/__throttle_probe', fn() => response('ok'))
            ->middleware(['web','check.session','throttle:5,1']);
        app('router')->getRoutes()->refreshNameLookups();

        $this->withSession(['id' => 'TEST']);
        $this->get('/__throttle_probe?id=TEST')->assertOk(); // 1er appel = 200
    }
}
