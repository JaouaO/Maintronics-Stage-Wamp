<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;

class RouteConstraintsStrictTest extends TestCase
{
    public function test_nouvelle_goes_to_create_not_edit(): void
    {
        $route = $this->app['router']
            ->getRoutes()
            ->match(Request::create('/interventions/nouvelle', 'GET'));

        $this->assertSame('interventions.create', $route->getName(),
            '"/interventions/nouvelle" ne doit pas matcher la route edit');
    }


}
