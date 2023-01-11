<?php

declare(strict_types=1);

namespace BackendBase\Domain\User\Query;

class GetAllUsers
{
    public function __construct(private int $offset, private int $limit)
    {
    }

    public function offset() : int
    {
        return $this->offset;
    }

    public function limit() : int
    {
        return $this->limit;
    }
}
