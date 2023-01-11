<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Persistence\Doctrine;

use BackendBase\Domain\User\Model\User;

class UserMapper
{
    public function __construct(private User $user)
    {
    }

    public function toDatabasePayload() : array
    {
        return [
            'id' => $this->user->id()->toString(),
            'email' => $this->user->email()->toString(),
            'first_name' => $this->user->firstName(),
            'last_name' => $this->user->lastName(),
            'created_at' => $this->user->createdAt()
                ->format(User::CREATED_AT_FORMAT),
        ];
    }
}
