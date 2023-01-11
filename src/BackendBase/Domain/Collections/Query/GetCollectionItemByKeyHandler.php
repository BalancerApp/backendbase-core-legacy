<?php

declare(strict_types=1);

namespace BackendBase\Domain\Collections\Query;

use BackendBase\Domain\Collections\Interfaces\CollectionQuery;
use BackendBase\Domain\Collections\Persistence\Doctrine\ResultObject\Collection;

class GetCollectionItemByKeyHandler
{
    private CollectionQuery $collectionQuery;

    public function __construct(CollectionQuery $collectionQuery)
    {
        $this->collectionQuery = $collectionQuery;
    }

    public function __invoke(GetCollectionItemByKey $query) : Collection
    {
        return $this->collectionQuery->findByKey($query->key());
    }
}
