<?php

declare(strict_types=1);

namespace UnitTest\Shared\ValueObject;

use BackendBase\Shared\ValueObject\Exception\InvalidPhoneNumber;
use BackendBase\Shared\ValueObject\PhoneNumber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    #[Test]
    public function shouldSuccessfullyInit() : void
    {
        $phoneNumber = new PhoneNumber('90', '555', '555  55 55');
        $this->assertSame('90', $phoneNumber->countryCode());
        $this->assertSame('555', $phoneNumber->areaCode());
        $this->assertSame('5555555', $phoneNumber->phoneNumber());
        $this->assertSame('+905555555555', $phoneNumber->getE164FormattedNumber());
    }

    #[Test]
    public function shouldSuccessfullyInitUsingNamedConstructor() : void
    {
        $phoneNumber = PhoneNumber::fromString('+90 555 555 5555');
        $this->assertSame('90', $phoneNumber->countryCode());
        $this->assertSame('555', $phoneNumber->areaCode());
        $this->assertSame('5555555', $phoneNumber->phoneNumber());
        $this->assertSame('+905555555555', $phoneNumber->getE164FormattedNumber());
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithNoPlusForTheFirstCharacter() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('90 555 555 5555');
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithInvalidPhoneNumberLength() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('+905555');
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithInvalidAreaCodeLength() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('+905555555');
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithInvalidCountryCodeLength() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('+5555555555');
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithInvalidPhoneNumberCharacter() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('+90555555555a');
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithInvalidAreaCodeCharacter() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('+90a555555555');
    }

    #[Test]
    public function shouldFailForInvalidPhoneNumberWithInvalidCountryCodeCharacter() : void
    {
        $this->expectException(InvalidPhoneNumber::class);
        PhoneNumber::fromString('+9a5555555555');
    }
}
