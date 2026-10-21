<?php

declare(strict_types=1);

namespace IntegrationTest\Persistence\Doctrine;

use BackendBase\Infrastructure\Persistence\Doctrine\Entity\Collection;
use BackendBase\Infrastructure\Persistence\Doctrine\Entity\User;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\SchemaValidator;
use Fixtures\DoctrineOrmTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Doctrine\UuidType;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

final class EntityManagerFactoryTest extends DoctrineOrmTestCase
{
    #[DataProvider('cacheProfiles')]
    public function testDiscoversAndValidatesAllEntityMappings(bool $debug, string $cacheClass): void
    {
        $manager    = $this->entityManager($debug);
        $factory    = $manager->getMetadataFactory();
        $metadata   = $factory->getAllMetadata();
        $validator  = new SchemaValidator($manager);
        $config     = $manager->getConfiguration();
        $user       = $manager->getClassMetadata(User::class);
        $collection = $manager->getClassMetadata(Collection::class);

        $this->assertCount(8, $metadata);
        $this->assertSame([], $validator->validateMapping());
        $this->assertSame('password_hash', $user->getColumnName('passwordHash'));
        $this->assertSame('parent_id', $collection->getColumnName('parentId'));
        $this->assertSame('uuid', $user->getTypeOfField('id'));
        $this->assertInstanceOf($cacheClass, $config->getMetadataCache());
        $this->assertInstanceOf($cacheClass, $config->getQueryCache());
        $this->assertInstanceOf($cacheClass, $config->getResultCache());
        $this->assertNotSame($config->getMetadataCache(), $config->getQueryCache());
    }

    public function testCreatesMultipleManagersWithoutRegisteringTheUuidTypeTwice(): void
    {
        $first  = $this->entityManager();
        $second = $this->entityManager();

        $this->assertNotSame($first, $second);
        $this->assertSame($this->connection, $second->getConnection());
        $this->assertInstanceOf(UuidType::class, Type::getType('uuid'));
    }

    /** @return list<array{bool, class-string}> */
    public static function cacheProfiles(): array
    {
        return [[true, ArrayAdapter::class], [false, PhpFilesAdapter::class]];
    }
}
