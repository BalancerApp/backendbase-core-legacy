<?php

declare(strict_types=1);

namespace BackendBase\Domain\IdentityAndAccess\Model;

use function implode;

class Person
{
    public function __construct(private string $firstName, private string $lastName, private ContactInformation $contactInformation)
    {
    }

    public function firstName() : string
    {
        return $this->firstName;
    }

    public function lastName() : string
    {
        return $this->lastName;
    }

    public function fullName() : string
    {
        return implode(' ', [$this->firstName, $this->lastName]);
    }

    public function contactInformation() : ContactInformation
    {
        return $this->contactInformation;
    }
}
