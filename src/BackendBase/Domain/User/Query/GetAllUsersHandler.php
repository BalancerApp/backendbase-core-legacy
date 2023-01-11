<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Query;

use BackendBase\Domain\User\Interfaces\UserQuery;
use BackendBase\Domain\User\Model\Users;

class GetAllUsersHandler
{
    public function __construct(private UserQuery $repository)
    {
    }

    public function __invoke(GetAllUsers $command) : Users
    {
        return $this->repository->getAllUsersPaginated($command->offset(), $command->limit());
    }
}
