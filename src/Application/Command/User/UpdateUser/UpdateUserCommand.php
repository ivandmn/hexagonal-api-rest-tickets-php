<?php

declare(strict_types=1);

namespace App\Application\Command\User\UpdateUser;

use App\Application\Command\CommandInterface;
use App\Application\Validation\Field;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;

/**
 * Partial update: null fields stay unchanged.
 */
final readonly class UpdateUserCommand implements CommandInterface
{
    private UserId $userId;
    private ?UserEmail $email;
    private ?UserName $name;

    public function __construct(string $userId, ?string $email, ?string $name)
    {
        $this->userId = Field::of('userId', static fn (): UserId => UserId::fromString($userId));
        $this->email  = null !== $email ? Field::of('email', static fn (): UserEmail => UserEmail::fromString($email)) : null;
        $this->name   = null !== $name ? Field::of('name', static fn (): UserName => UserName::fromString($name)) : null;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getEmail(): ?UserEmail
    {
        return $this->email;
    }

    public function getName(): ?UserName
    {
        return $this->name;
    }
}
