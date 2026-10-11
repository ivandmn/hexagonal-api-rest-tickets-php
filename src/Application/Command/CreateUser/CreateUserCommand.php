<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

use App\Application\Command\CommandInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserName;

final readonly class CreateUserCommand implements CommandInterface
{
    private UserEmail $email;
    private UserName $name;

    public function __construct(string $email, string $name)
    {
        $this->email = UserEmail::fromString($email);
        $this->name  = UserName::fromString($name);
    }

    public function getEmail(): UserEmail
    {
        return $this->email;
    }

    public function getName(): UserName
    {
        return $this->name;
    }
}
