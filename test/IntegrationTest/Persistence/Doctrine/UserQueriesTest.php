<?php

declare(strict_types=1);

namespace IntegrationTest\Persistence\Doctrine;

use BackendBase\Domain\Shared\Exception\InvalidArgument;
use BackendBase\Domain\User\Exception\UserNotFound;
use BackendBase\Domain\User\Persistence\Doctrine\SqlQuery\GetAllUsers;
use BackendBase\Domain\User\Persistence\Doctrine\SqlQuery\GetUserByEmail;
use BackendBase\Domain\User\Persistence\Doctrine\SqlQuery\GetUserById;
use Fixtures\Authentication;
use Fixtures\PostgresTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_column;
use function json_decode;
use function json_encode;

final class UserQueriesTest extends PostgresTestCase
{
    public function testHydratesSnakeCaseColumnsIntoTypedUserProperties() : void
    {
        $user = GetUserById::execute($this->connection, ['userId' => Authentication::USER_ID]);

        $this->assertSame(Authentication::USER_ID, $user->id());
        $this->assertSame('Alice', $user->firstName());
        $this->assertSame('Editor', $user->lastName());
        $this->assertSame('alice@example.test', $user->email());
        $this->assertSame('fixture-password-hash', $user->passwordHash());
        $this->assertSame('argon2id', $user->passwordHashAlgo());
        $this->assertSame(1, $user->isActive());
        $this->assertSame('2026-01-01 12:00:00', $user->createdAt());
        $this->assertSame('Alice', json_decode(json_encode($user), true)['firstName']);
    }

    public function testFindsEmailAddressesWithoutCaseSensitivity() : void
    {
        $user = GetUserByEmail::execute($this->connection, ['email' => 'ALICE@EXAMPLE.TEST']);

        $this->assertSame(Authentication::USER_ID, $user->id());
    }

    public function testReturnsOrderedUsersAndJoinedRoleTitlesWithoutDeletedRows() : void
    {
        $users = GetAllUsers::execute($this->connection, ['offset' => 0, 'limit' => 25]);
        $rows = $users->toArray();

        $this->assertSame(['Alice', 'Inactive', 'Bob'], array_column($rows, 'firstName'));
        $this->assertSame(['Editor', 'Editor', 'Reader'], array_column($rows, 'roleStr'));
        $this->assertSame(3, $users->count());
        $this->assertSame($rows, json_decode(json_encode($users), true));
    }

    public function testBindsIntegerPaginationParameters() : void
    {
        $users = GetAllUsers::execute($this->connection, ['offset' => 1, 'limit' => 1]);

        $this->assertSame(['Inactive'], array_column($users->toArray(), 'firstName'));
    }

    public function testReturnsAnEmptyCollectionAfterTheLastPage() : void
    {
        $users = GetAllUsers::execute($this->connection, ['offset' => 20, 'limit' => 5]);

        $this->assertSame([], $users->toArray());
        $this->assertSame('[]', json_encode($users));
    }

    #[DataProvider('unavailableUsers')]
    public function testReportsDeletedAndMissingUsersAsNotFound(string $id) : void
    {
        $this->expectException(UserNotFound::class);
        GetUserById::execute($this->connection, ['userId' => $id]);
    }

    public static function unavailableUsers() : array
    {
        return [
            'deleted' => ['33333333-3333-4333-8333-333333333333'],
            'missing' => ['99999999-9999-4999-8999-999999999999'],
        ];
    }

    public function testDoesNotTreatSqlTextInAnEmailAsAQuery() : void
    {
        $this->expectException(UserNotFound::class);
        GetUserByEmail::execute($this->connection, ['email' => "' OR 1=1 --"]);
    }

    public function testRejectsMissingQueryParameters() : void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessage('Query needs parameter named: userId');
        GetUserById::execute($this->connection, []);
    }
}
