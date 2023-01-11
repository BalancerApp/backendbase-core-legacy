<?php

declare(strict_types=1);

namespace BackendBase\Domain\Collections\Query;

class GetCollectionItemById
{
    public function __construct(private string $id)
    {
    }

    public function payload() : array
    {
        return [
            'id' => $this->id,
        ];
    }

    public function id() : string
    {
        return $this->id;
    }
}
