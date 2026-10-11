<?php

declare(strict_types=1);

namespace App\Application\DTO\Views;

use App\Domain\Model\User;

/**
 * Summary of the user a task is assigned to.
 */
final readonly class AssignedUserView
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->getId()->value(), $user->getName()->value());
    }
}
