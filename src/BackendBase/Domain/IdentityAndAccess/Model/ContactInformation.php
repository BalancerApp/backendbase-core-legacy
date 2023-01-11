<?php

declare(strict_types=1);

namespace BackendBase\Domain\IdentityAndAccess\Model;

use BackendBase\Shared\ValueObject\Interfaces\Email;

class ContactInformation
{
    public function __construct(private Email $email, private string $mobile)
    {
    }

    public function email() : Email
    {
        return $this->email;
    }

    public function mobile() : string
    {
        return $this->mobile;
    }
}
