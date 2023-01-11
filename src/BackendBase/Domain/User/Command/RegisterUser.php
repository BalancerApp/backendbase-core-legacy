<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Command;

class RegisterUser
{
    public function __construct(private string $id, private string $firstName, private string $lastName, private string $email)
    {
    }

    public function id()
    {
        return $this->id;
    }

    public function firstName()
    {
        return $this->firstName;
    }

    public function lastName()
    {
        return $this->lastName;
    }

    public function email()
    {
        return $this->email;
    }
}
