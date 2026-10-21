<?php

declare(strict_types=1);

namespace UnitTest\Shared\Services;

use BackendBase\Infrastructure\Persistence\Doctrine\Repository\ContentRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\ORM\EntityManager;
use Fixtures\Authentication;
use PHPUnit\Framework\TestCase;
use Redislabs\Module\ReJSON\ReJSON;

final class ContentSlugCompatibilityTest extends TestCase
{
    public function testPreservesPublicContentSlugsAfterUpdatingTheUuidShortener(): void
    {
        $statement = $this->createMock(Statement::class);
        $statement->expects($this->once())->method('fetch')->willReturn([
            'id' => Authentication::USER_ID,
            'title' => 'İstanbul Rehberi',
            'images' => '[]',
            'metadata' => '{}',
            'created_at' => '2026-01-01 12:00:00',
            'is_deleted' => 0,
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeQuery')
            ->with(
                $this->stringContains('FROM public.contents'),
                $this->callback(static function (array $parameters): bool {
                    return $parameters['id'] === Authentication::USER_ID;
                }),
            )->willReturn($statement);
        $repository = new ContentRepository(
            $this->createStub(EntityManager::class),
            $connection,
            $this->createStub(ReJSON::class),
        );

        $content = $repository->getContentByIdForClient(Authentication::USER_ID);

        $this->assertSame('istanbul-rehberi-8XYg78F7GtZaDFW5QMf645', $content['slug']);
        $this->assertSame(Authentication::USER_ID, $content['id']);
        $this->assertSame('01.01.2026', $content['publishDate']);
    }
}
