<?php

declare(strict_types=1);

namespace UnitTest\Shared\Factory;

use BackendBase\Shared\Factory\ReJSONFactory;
use JsonException;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use Redislabs\Module\ReJSON\ReJSON;
use stdClass;

use function array_keys;

final class ReJSONFactoryTest extends TestCase
{
    public function testSerializesNestedValuesForTheRootPathWithoutDoubleEncoding() : void
    {
        $redisJson = $this->redisJson([
            'JSON.SET', 'lookup:item', '.', '{"name":"Editor","enabled":true,"items":[1,null]}',
        ], 'OK');

        $this->assertSame('OK', $redisJson->set('lookup:item', '.', [
            'name' => 'Editor', 'enabled' => true, 'items' => [1, null],
        ]));
    }

    public function testDecodesObjectsAndPreservesNestedScalarTypes() : void
    {
        $redisJson = $this->redisJson(['JSON.GET', 'lookup:item', '.metadata'],
            '{"enabled":false,"count":0,"items":[1,null]}');

        $data = $redisJson->get('lookup:item', '.metadata');

        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertFalse($data->enabled);
        $this->assertSame(0, $data->count);
        $this->assertSame([1, null], $data->items);
    }

    public function testReturnsNullWhenTheDocumentDoesNotExist() : void
    {
        $this->assertNull($this->redisJson(['JSON.GET', 'lookup:missing', '.'], false)->get('lookup:missing', '.'));
    }

    public function testDecodesMultipleDocumentsInTheirOriginalOrder() : void
    {
        $redisJson = $this->redisJson(['JSON.MGET', 'lookup:first', 'lookup:second', '.'],
            ['{"name":"first"}', '{"name":"second"}']);

        $data = $redisJson->mget('lookup:first', 'lookup:second', '.');

        $this->assertCount(2, $data);
        $this->assertSame('first', $data[0]->name);
        $this->assertSame('second', $data[1]->name);
    }

    public function testDeletesTheRequestedPathAndReturnsTheDeletedCount() : void
    {
        $this->assertSame(1, $this->redisJson(['JSON.DEL', 'lookup:item', '.metadata'], 1)
            ->del('lookup:item', '.metadata'));
    }

    public function testPreservesEmptyObjectsAndNumericObjectProperties(): void
    {
        $redisJson = $this->redisJson(['JSON.GET', 'lookup:item', '.'],
            '{"emptyObject":{},"emptyArray":[],"numericObject":{"0":"zero","1":"one"},"items":[{}]}');

        $data = $redisJson->get('lookup:item', '.');

        $this->assertInstanceOf(stdClass::class, $data->emptyObject);
        $this->assertSame([], $data->emptyArray);
        $this->assertInstanceOf(stdClass::class, $data->numericObject);
        $this->assertSame('zero', $data->numericObject->{'0'});
        $this->assertInstanceOf(stdClass::class, $data->items[0]);
    }

    #[DataProvider('scalarDocuments')]
    public function testPreservesScalarDocumentTypes(string $json, $expected): void
    {
        $data = $this->redisJson(['JSON.GET', 'lookup:item', '.'], $json)->get('lookup:item', '.');

        $this->assertSame($expected, $data);
    }

    public static function scalarDocuments(): array
    {
        return [['false', false], ['0', 0], ['1.5', 1.5], ['"text"', 'text'], ['null', null], ['[]', []]];
    }

    public function testKeepsMissingAndDuplicateKeysInTheMultipleGetResult(): void
    {
        $redisJson = $this->redisJson(
            ['JSON.MGET', 'lookup:item', 'lookup:missing', 'lookup:item', '.'],
            ['{"name":"item"}', false, '{"name":"item"}']
        );

        $data = $redisJson->mget('lookup:item', 'lookup:missing', 'lookup:item', '.');

        $this->assertSame([0, 1, 2], array_keys($data));
        $this->assertSame('item', $data[0]->name);
        $this->assertNull($data[1]);
        $this->assertSame('item', $data[2]->name);
    }

    public function testPreservesFormattingOptionsAndMultiplePathResults(): void
    {
        $redisJson = $this->redisJson(
            ['JSON.GET', 'lookup:item', 'INDENT', '  ', '.name', '.count'],
            '{".name":"Editor",".count":0}'
        );

        $data = $redisJson->get('lookup:item', 'INDENT', '  ', '.name', '.count');

        $this->assertInstanceOf(stdClass::class, $data);
        $this->assertSame('Editor', $data->{'.name'});
        $this->assertSame(0, $data->{'.count'});
    }

    public function testRejectsMalformedJsonInsteadOfReturningAPartialResult(): void
    {
        $redisJson = $this->redisJson(['JSON.GET', 'lookup:item', '.'], '{invalid');

        $this->expectException(JsonException::class);
        $redisJson->get('lookup:item', '.');
    }

    private function redisJson(array $command, $response) : ReJSON
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects($this->once())->method('rawCommand')->with(...$command)->willReturn($response);
        $container = new ServiceManager(['services' => [Redis::class => $redis]]);

        return (new ReJSONFactory())($container, ReJSON::class);
    }
}
