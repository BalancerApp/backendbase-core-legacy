<?php

declare(strict_types=1);

namespace UnitTest\Shared\ValueObject;

use BackendBase\Shared\ValueObject\Email;
use BackendBase\Shared\ValueObject\Exception\InvalidEmailAddress;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    #[Test]
    public function shouldSuccessfullyInit() : void
    {
        $email = new Email('mehmet@mkorkmaz.com');
        $this->assertSame('mehmet@mkorkmaz.com', $email->getEmail());
    }

    #[Test]
    public function shouldFailForInvalidEmail() : void
    {
        $this->expectException(InvalidEmailAddress::class);
        new Email('mehmet@mkorkmaz..com');
    }
}
