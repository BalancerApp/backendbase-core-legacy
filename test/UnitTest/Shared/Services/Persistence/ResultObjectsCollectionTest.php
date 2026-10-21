<?php

declare(strict_types=1);

namespace UnitTest\Shared\Services\Persistence;

use BackendBase\Domain\Collections\Model\Collections;
use BackendBase\Domain\Collections\Persistence\Doctrine\ResultObject\Collection;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResultObjectsCollectionTest extends TestCase
{
    public function testIteratesInOrderAndRewindsAfterAppending(): void
    {
        $first       = $this->collection('first');
        $second      = $this->collection('second');
        $third       = $this->collection('third');
        $collections = new Collections([$first, $second]);

        $this->assertSame([0 => $first, 1 => $second], iterator_to_array($collections));
        $this->assertFalse($collections->valid());

        $collections->push($third);

        $this->assertSame(3, $collections->count());
        $this->assertSame([0 => $first, 1 => $second, 2 => $third], iterator_to_array($collections));
    }

    public function testConvertsResultObjectsToArraysAndJson(): void
    {
        $first       = $this->collection('first');
        $second      = $this->collection('second');
        $collections = new Collections([$first, $second]);
        $expected    = [$first->toArray(), $second->toArray()];

        $this->assertSame($expected, $collections->toArray());
        $this->assertSame(
            $expected,
            json_decode(json_encode($collections, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testHandlesAnEmptyCollection(): void
    {
        $collections = new Collections(null);

        $this->assertSame(0, $collections->count());
        $this->assertFalse($collections->valid());
        $this->assertSame([], iterator_to_array($collections));
        $this->assertSame([], $collections->toArray());
        $this->assertSame('[]', json_encode($collections, JSON_THROW_ON_ERROR));
    }

    private function collection(string $key): Collection
    {
        $collection       = new Collection();
        $collection->id   = $key;
        $collection->key  = $key;
        $collection->name = $key;

        return $collection;
    }
}
