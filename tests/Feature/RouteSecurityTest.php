<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class RouteSecurityTest extends TestCase
{
    public function test_login_is_reachable(): void
    {
        $this->get('/authentification')->assertOk();
    }

    public function test_protected_requires_session(): void
    {
        $this->get('/interventions')->assertRedirect(); // vers /authentification
    }

    public function test_with_session_has_security_headers(): void
    {
        // Route factice protégée (évite DB)
        Route::get('/__protected_headers', fn() => response('ok'))
            ->middleware(['web','check.session','security.headers']);
        app('router')->getRoutes()->refreshNameLookups();

        $this->withSession(['id' => 'TEST']);
        $resp = $this->get('/__protected_headers?id=TEST');
        $resp->assertOk();

        $this->assertTrue($resp->headers->has('X-Frame-Options'), 'X-Frame-Options manquant');
        $this->assertTrue($resp->headers->has('X-Content-Type-Options'), 'X-Content-Type-Options manquant');
    }
}
