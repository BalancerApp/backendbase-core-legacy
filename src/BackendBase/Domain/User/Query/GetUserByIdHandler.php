<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Query;

use BackendBase\Domain\User\Interfaces\UserQuery;
use BackendBase\Domain\User\Model\UserId;
use BackendBase\Domain\User\Persistence\Doctrine\ResultObject\User;

class GetUserByIdHandler
{
    public function __construct(private UserQuery $query)
    {
    }

    public function __invoke(GetUserById $command) : User
    {
        return $this->query
            ->getUserById(UserId::createFromString($command->id()));
    }
}
