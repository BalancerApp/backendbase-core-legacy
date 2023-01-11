<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Command;

use BackendBase\Domain\User\Exception\UserNotFound;
use BackendBase\Domain\User\Interfaces\UserRepository;
use BackendBase\Domain\User\Model\UserId;
use BackendBase\Shared\Exception\ExecutionFailed;

class UnregisterUserHandler
{
    public function __construct(private UserRepository $repository)
    {
    }

    /**
     * @throws UserNotFound
     * @throws ExecutionFailed
     *
     * @var UnregisterUser
     */
    public function __invoke(UnregisterUser $command) : void
    {
        $this->repository->unregisterUser(UserId::createFromString($command->id()));
    }
}
