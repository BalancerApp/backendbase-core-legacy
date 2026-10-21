<?php

declare(strict_types=1);

namespace Fixtures;

use BackendBase\Shared\Factory\Doctrine\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function file_get_contents;
use function getenv;
use function is_file;

abstract class PostgresTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->initializeConnection();
        } catch (Throwable $error) {
            $this->tearDown();

            throw $error;
        }
    }

    private function initializeConnection(): void
    {
        $socket = getenv('PHP85_TEST_POSTGRES_SOCKET');
        if ($socket === false) {
            $this->markTestSkipped('Run bash test/run-characterization-tests.sh for isolated PostgreSQL tests.');
        }

        if (! is_file($socket . '/backendbase-test-cluster')) {
            throw new RuntimeException('The PostgreSQL socket must belong to the isolated test runner.');
        }

        $container        = new ServiceManager([
            'services' => [
                'config' => [
                    'doctrine' => [
                        'dbal' => [
                            'driver' => 'pdo_pgsql',
                            'host' => $socket,
                            'port' => 55485,
                            'user' => 'php85_test',
                            'dbname' => 'postgres',
                        ],
                    ],
                ],
            ],
        ]);
        $this->connection = (new ConnectionFactory())($container, Connection::class);
        $this->connection->beginTransaction();
        $this->connection->executeStatement(file_get_contents(__DIR__ . '/postgres.sql'));
    }

    protected function tearDown(): void
    {
        try {
            $this->closeConnection();
        } finally {
            parent::tearDown();
        }
    }

    private function closeConnection(): void
    {
        if (! isset($this->connection)) {
            return;
        }

        if ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->connection->close();
    }
}
