<?php

declare(strict_types=1);

namespace BackendBase\Shared\ValueObject;

use BackendBase\Shared\ValueObject\Exception\InvalidEmailAddress;
use InvalidArgumentException;
use Webmozart\Assert\Assert;
use BackendBase\Shared\ValueObject\Interfaces\Email as EmailInterface;

final class Email implements EmailInterface
{
    private string $email;

    public function __construct(string $email)
    {
        try {
            Assert::email($email);
        } catch (InvalidArgumentException $e) {
            throw InvalidEmailAddress::create('Invalid email address: ' . $email);
        }

        $this->email = $email;
    }

    public static function createFromString(string $email) : self
    {
        return new self($email);
    }

    public function getEmail() : string
    {
        return $this->toString();
    }

    public function toString() : string
    {
        return $this->email;
    }
}
