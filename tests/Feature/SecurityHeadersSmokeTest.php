<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class SecurityHeadersSmokeTest extends TestCase
{
    public function test_protected_route_has_security_headers_in_local(): void
    {
        Route::get('/__protected_smoke', fn() => response('ok'))
            ->middleware(['web','check.session','security.headers']);
        app('router')->getRoutes()->refreshNameLookups();

        config(['app.env' => 'local']);
        $this->withSession(['id' => 'TEST']);

        $resp = $this->get('/__protected_smoke?id=TEST');
        $resp->assertOk();

        $this->assertTrue($resp->headers->has('X-Content-Type-Options'), 'Header X-Content-Type-Options absent');
    }
}
