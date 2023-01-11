<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Command;

use BackendBase\Domain\User\Interfaces\UserRepository;
use BackendBase\Domain\User\Model\UserId;

class UpdateUserPartiallyHandler
{
    public function __construct(private UserRepository $repository)
    {
    }

    public function __invoke(UpdateUserPartially $command) : void
    {
        $payload = $command->payload();
        $this->repository->updateUserInfo(UserId::createFromString($command->id()), $payload);
    }
}
