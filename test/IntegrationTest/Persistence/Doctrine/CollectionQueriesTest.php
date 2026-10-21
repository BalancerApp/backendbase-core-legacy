<?php

declare(strict_types=1);

namespace IntegrationTest\Persistence\Doctrine;

use BackendBase\Domain\Collections\Exception\CollectionNotFound;
use BackendBase\Domain\Collections\Persistence\Doctrine\SqlQuery\GetCollectionItem;
use Fixtures\PostgresTestCase;
use function array_column;
use function json_decode;
use function json_encode;

final class CollectionQueriesTest extends PostgresTestCase
{
    private const ROOT_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const CHILD_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    public function testHydratesCollectionPropertiesAndDecodesJsonMetadata() : void
    {
        $item = (new GetCollectionItem($this->connection))->byId(self::CHILD_ID);

        $this->assertSame(self::CHILD_ID, $item->id());
        $this->assertSame(self::ROOT_ID, $item->parentId());
        $this->assertSame('Child 10', $item->name());
        $this->assertSame(1, $item->isActive());
        $this->assertSame(['rank' => 10, 'enabled' => true], [
            'rank' => $item->metadata()['rank'], 'enabled' => $item->metadata()['enabled'],
        ]);
        $this->assertEquals($item->toArray(), json_decode(json_encode($item), true));
    }

    public function testLooksUpKeysAndIncludesTheParentKey() : void
    {
        $item = (new GetCollectionItem($this->connection))->byKey('lt:child-ten');

        $this->assertSame(self::CHILD_ID, $item->id());
        $this->assertSame('lt:root', $item->parentKey());
    }

    public function testScopesSlugLookupToItsParent() : void
    {
        $query = new GetCollectionItem($this->connection);
        $this->assertSame(self::CHILD_ID, $query->bySlug(self::ROOT_ID, 'child-ten')->id());

        $this->expectException(CollectionNotFound::class);
        $query->bySlug('eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee', 'child-ten');
    }

    public function testReturnsOnlyChildrenInNameOrderAndPreservesNullMetadata() : void
    {
        $items = (new GetCollectionItem($this->connection))->subItemsById(self::ROOT_ID);
        $rows = $items->toArray();

        $this->assertSame(['Child 10', 'Child 2'], array_column($rows, 'name'));
        $this->assertNull($rows[1]['metadata']);
        $this->assertSame(2, $items->count());
        $this->assertEquals($rows, json_decode(json_encode($items), true));
    }

    public function testPaginatesChildren() : void
    {
        $items = (new GetCollectionItem($this->connection))->subItemsById(self::ROOT_ID, 1, 1);

        $this->assertSame(['Child 2'], array_column($items->toArray(), 'name'));
    }

    public function testUsesNullParentToSelectRootItems() : void
    {
        $items = (new GetCollectionItem($this->connection))->subItemsById(null);

        $this->assertEqualsCanonicalizing(['lt:root', 'lt:other'], array_column($items->toArray(), 'key'));
    }

    public function testReturnsAnEmptyCollectionWhenThereAreNoChildren() : void
    {
        $items = (new GetCollectionItem($this->connection))->subItemsById(self::CHILD_ID);

        $this->assertSame([], $items->toArray());
        $this->assertSame('[]', json_encode($items));
    }

    public function testExcludesDeletedItemsFromDirectLookup() : void
    {
        $this->expectException(CollectionNotFound::class);
        (new GetCollectionItem($this->connection))->byId('dddddddd-dddd-4ddd-8ddd-dddddddddddd');
    }
}
