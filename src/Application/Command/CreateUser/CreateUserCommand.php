<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

use App\Application\Command\CommandInterface;

final readonly class CreateUserCommand implements CommandInterface
{
    public function __construct(
        private string $email,
        private string $name,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
