<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ThrottleWriteTest extends TestCase
{
    public function test_suggest_num_throttled_smoke(): void
    {
        // Route factice POST avec throttle
        Route::post('/__throttle_write', fn() => response('ok'))
            ->middleware(['web','check.session','throttle:5,1']);
        app('router')->getRoutes()->refreshNameLookups();

        $this->withSession(['id' => 'TEST']);
        $this->post('/__throttle_write?id=TEST', ['_token' => csrf_token()])->assertOk();
    }
}
