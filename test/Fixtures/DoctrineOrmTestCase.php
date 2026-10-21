<?php

declare(strict_types=1);

namespace Fixtures;

use BackendBase\Shared\Factory\Doctrine\EntityManagerFactory;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\ORM\EntityManager;
use Laminas\ServiceManager\ServiceManager;

use function getenv;

abstract class DoctrineOrmTestCase extends PostgresTestCase
{
    protected function entityManager(bool $debug = true): EntityManager
    {
        $container = new ServiceManager([
            'services' => [
                Connection::class => $this->connection,
                'config' => [
                    'debug' => $debug,
                    'app' => ['data_dir' => getenv('PHP85_TEST_POSTGRES_SOCKET') . '/orm'],
                    'doctrine' => ['namespace-for-generator' => 'BackendBase\\Tests'],
                ],
            ],
        ]);

        return (new EntityManagerFactory())($container, EntityManager::class);
    }
}
