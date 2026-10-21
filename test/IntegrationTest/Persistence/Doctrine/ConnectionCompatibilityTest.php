<?php

declare(strict_types=1);

namespace IntegrationTest\Persistence\Doctrine;

use BackendBase\Shared\Factory\Doctrine\ConnectionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\PgSQL\Driver;
use Doctrine\DBAL\ParameterType;
use Fixtures\PostgresTestCase;
use Laminas\ServiceManager\ServiceManager;
use Pdo\Pgsql;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConnectionCompatibilityTest extends PostgresTestCase
{
    public function testKeepsServerSidePreparesDisabledByDefault(): void
    {
        $native = $this->connection->getWrappedConnection();

        $this->assertTrue($native->getAttribute(Pgsql::ATTR_DISABLE_PREPARES));
    }

    #[DataProvider('prepareProfiles')]
    public function testPreservesThePostgresqlPrepareOption(bool $disablePrepares, string|null $driverClass): void
    {
        $parameters                  = $this->connection->getParams();
        $parameters['driverOptions'] = [Pgsql::ATTR_DISABLE_PREPARES => $disablePrepares];
        if ($driverClass !== null) {
            $parameters['driverClass'] = $driverClass;
        }

        $container  = new ServiceManager(['services' => ['config' => ['doctrine' => ['dbal' => $parameters]]]]);
        $connection = (new ConnectionFactory())($container, Connection::class);

        try {
            $this->assertSame(7, $connection->fetchOne(
                'SELECT CAST(:value AS INTEGER)',
                ['value' => 7],
                ['value' => ParameterType::INTEGER],
            ));
            $native = $connection->getWrappedConnection();
            $this->assertSame($disablePrepares, $native->getAttribute(Pgsql::ATTR_DISABLE_PREPARES));
        } finally {
            $connection->close();
        }
    }

    /** @return list<array{bool, class-string|null}> */
    public static function prepareProfiles(): array
    {
        return [[true, null], [false, null], [true, Driver::class]];
    }
}
