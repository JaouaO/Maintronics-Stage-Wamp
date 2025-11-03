<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Stub global pour éviter les 500 dans CheckSession
        $this->app->bind(\App\Services\CheckAutorisationsService::class, function () {
            return new class {
                public function checkAutorisations($id, $ip)
                {
                    return [
                        'success' => true,
                        'data' => (object)[
                            'CodeAgSal'          => 'DOAG',
                            'CodeSal'            => 'TEST',
                            'Util'               => 'TEST',
                            'agences_autorisees' => ['DOAG'],
                            'defaultAgence'      => 'DOAG',
                        ],
                    ];
                }
            };
        });
    }
}
