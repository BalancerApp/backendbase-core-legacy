<?php

declare(strict_types=1);

namespace UnitTest\Shared\Services\Persistence;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class PostgreSqlPlatformCompatibilityTest extends TestCase
{
    public function testPreservesPostgresqlBooleanLiteralConversion(): void
    {
        $platform = new PostgreSqlPlatform();

        $this->assertSame(['true', 'false', 'true', 'false', 'NULL'], $platform->convertBooleans([
            'true',
            'false',
            1,
            0,
            null,
        ]));
    }

    public function testPreservesTheErrorForAnUnrecognizedBooleanLiteral(): void
    {
        $platform = new PostgreSqlPlatform();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("Unrecognized boolean literal 'invalid'");
        $platform->convertBooleans('invalid');
    }
}
