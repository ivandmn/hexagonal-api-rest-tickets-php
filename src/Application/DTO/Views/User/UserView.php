<?php

declare(strict_types=1);

namespace App\Application\DTO\Views\User;

use App\Domain\Model\User;

/**
 * Read model of a user, returned by the queries.
 */
final readonly class UserView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->getId()->value(),
            $user->getEmail()->value(),
            $user->getName()->value(),
            $user->getCreatedAt(),
            $user->getUpdatedAt(),
        );
    }
}
