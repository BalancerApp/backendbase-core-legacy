<?php

declare(strict_types=1);

namespace IntegrationTest\Persistence\Doctrine;

use BackendBase\Infrastructure\Persistence\Doctrine\Entity\Collection;
use BackendBase\Infrastructure\Persistence\Doctrine\Entity\User;
use DateTimeImmutable;
use Doctrine\Persistence\Proxy;
use Fixtures\Authentication;
use Fixtures\DoctrineOrmTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function json_decode;
use function serialize;
use function unserialize;

final class OrmPersistenceTest extends DoctrineOrmTestCase
{
    #[DataProvider('cacheProfiles')]
    public function testPersistsReloadsUpdatesAndDeletesAUser(bool $debug): void
    {
        $manager = $this->entityManager($debug);
        $user    = new User();
        $user->setId('99999999-9999-4999-8999-999999999999');
        $user->setEmail('orm@example.test');
        $user->setFirstName('Orm');
        $user->setLastName('Fixture');
        $user->setPasswordHash('fixture-hash');
        $user->setPasswordHashAlgo('argon2id');
        $user->setRole('editor');
        $user->setCreatedAt(new DateTimeImmutable('2026-01-01 12:00:00+00:00'));
        $manager->persist($user);
        $manager->flush();
        $manager->clear();

        $reloaded = $manager->find(User::class, '99999999-9999-4999-8999-999999999999');

        $this->assertInstanceOf(User::class, $reloaded);
        $this->assertSame('99999999-9999-4999-8999-999999999999', $reloaded->id());
        $this->assertSame('Orm Fixture', $reloaded->getFullName());
        $this->assertInstanceOf(DateTimeImmutable::class, $reloaded->createdAt());
        $createdAt = $reloaded->createdAt();
        $this->assertSame('2026-01-01 12:00:00', $createdAt->format('Y-m-d H:i:s'));

        $reloaded->setFirstName('Updated');
        $manager->flush();
        $manager->clear();
        $updated = $manager->find(User::class, '99999999-9999-4999-8999-999999999999');
        $this->assertSame('Updated Fixture', $updated->getFullName());

        $manager->remove($updated);
        $manager->flush();
        $manager->clear();

        $this->assertNull($manager->find(User::class, '99999999-9999-4999-8999-999999999999'));
    }

    public function testHydratesCollectionJsonAndNullableParent(): void
    {
        $manager = $this->entityManager();
        $child   = $manager->find(Collection::class, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $root    = $manager->find(Collection::class, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');

        $this->assertSame(['rank' => 10, 'enabled' => true], $child->metadata());
        $this->assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $child->parentId());
        $this->assertNull($root->parentId());
    }

    #[DataProvider('cacheProfiles')]
    public function testExecutesTheConfiguredPostgresqlJsonDqlFunctions(bool $debug): void
    {
        $manager = $this->entityManager($debug);
        $query   = $manager->createQuery(
            'SELECT JSON_GET_TEXT(item.metadata, \'rank\') FROM ' . Collection::class . ' item WHERE item.id = :id',
        );
        $query->setParameter('id', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'uuid');

        $this->assertSame('10', $query->getSingleScalarResult());

        $jsonQuery = $manager->createQuery(
            'SELECT JSON_GET(item.metadata, \'enabled\') FROM ' . Collection::class . ' item WHERE item.id = :id',
        );
        $jsonQuery->setParameter('id', 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'uuid');

        $this->assertTrue(json_decode($jsonQuery->getSingleScalarResult()));
    }

    #[DataProvider('cacheProfiles')]
    public function testInitializesClonesAndSerializesAProxy(bool $debug): void
    {
        $manager = $this->entityManager($debug);
        $proxy   = $manager->getReference(User::class, Authentication::USER_ID);

        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertFalse($proxy->__isInitialized());
        $copy = clone $proxy;
        $this->assertSame('Alice Editor', $copy->getFullName());
        $this->assertSame('Alice Editor', $proxy->getFullName());

        $restored = unserialize(serialize($proxy));

        $this->assertSame(Authentication::USER_ID, $restored->id());
        $this->assertSame('Alice Editor', $restored->getFullName());
    }

    /** @return list<array{bool}> */
    public static function cacheProfiles(): array
    {
        return [[true], [false]];
    }
}
