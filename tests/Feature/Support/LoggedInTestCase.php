<?php

namespace Tests\Feature\Support;

use Tests\TestCase;
use App\Services\CheckAutorisationsService;

abstract class LoggedInTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Session de test
        $this->withSession([
            'id' => 'TEST',
            'CodeAgSal' => 'DOAG',
            'codeSal' => 'TEST',
            'agences_autorisees' => ['DOAG'],
            'defaultAgence' => 'DOAG',
        ]);

        // (Facultatif mais utile) : stub du service d'autorisations
        $this->app->bind(CheckAutorisationsService::class, function () {
            return new class {
                public function checkAutorisations($id, $ip)
                {
                    return ['success' => true, 'data' => (object)[
                        'CodeAgSal' => 'DOAG',
                        'CodeSal' => 'TEST',
                        'Util' => 'TEST',
                        'agences_autorisees' => ['DOAG'],
                        'defaultAgence' => 'DOAG',
                    ]];
                }
            };
        });
    }

    protected function getProtected(string $path, array $query = [], array $server = [])
    {
        $this->withSession(['id' => 'TEST']);
        $query = array_merge(['id' => 'TEST'], $query);
        return $this->get($path . '?' . http_build_query($query), $server);
    }


}
