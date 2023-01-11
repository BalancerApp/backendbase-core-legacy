<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Persistence\Doctrine\SqlQuery;

use BackendBase\Domain\Shared\Exception\ExecutionFailed;
use BackendBase\Domain\Shared\Exception\InvalidArgument;
use BackendBase\Domain\User\Exception\UserNotFound;
use BackendBase\Domain\User\Persistence\Doctrine\ResultObject\User;
use BackendBase\Shared\Services\Persistence\SqlQuery;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\FetchMode;
use Throwable;
use function array_key_exists;
use function count;
use function sprintf;

final class GetUserById
{
    use SqlQuery;

    private static $sql = <<<SQL
        SELECT id, first_name, last_name, email, password_hash, password_hash_algo, is_active, created_at, role
          FROM admin.users
         WHERE id=:userId
           AND is_deleted = 0 
SQL;

    public static function execute(Connection $connection, array $parameters) : ?User
    {
        if (! array_key_exists('userId', $parameters)) {
            throw InvalidArgument::create('Query needs parameter named: userId');
        }
        $query     = new static($connection);
        $statement = $query->executeQuery(self::$sql, $parameters);
        try {
            $records = $statement->fetchAll(FetchMode::CUSTOM_OBJECT, User::class);
            if ((is_countable($records) ? count($records) : 0) === 0) {
                throw UserNotFound::create(sprintf('User not found by id: %s', $parameters['userId']));
            }

            return $records[0];
        } catch (Throwable $exception) {
            if ($exception instanceof  UserNotFound) {
                throw $exception;
            }
            throw ExecutionFailed::create($exception->getMessage());
        }
    }
}
