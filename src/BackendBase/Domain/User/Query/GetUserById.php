<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Query;

class GetUserById
{
    public function __construct(private string $id)
    {
    }

    public function id() : string
    {
        return $this->id;
    }
}
