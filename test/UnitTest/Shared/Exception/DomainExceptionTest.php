<?php

declare(strict_types=1);

namespace UnitTest\Shared\Exception;

use BackendBase\Shared\ValueObject\Exception\InvalidPrivateTaxIdNumber;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testPreservesTheErrorMessageAndStatusWithoutCreatingDynamicProperties(): void
    {
        $exception = InvalidPrivateTaxIdNumber::create('Invalid tax ID', ['field' => 'taxId']);

        $this->assertSame('Invalid tax ID', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
    }
}
