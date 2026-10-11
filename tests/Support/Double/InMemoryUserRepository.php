<?php

declare(strict_types=1);

namespace App\Tests\Support\Double;

use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;

/**
 * Test double: keeps users in memory so handler tests can assert on state.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function __construct(User ...$users)
    {
        foreach ($users as $user) {
            $this->users[$user->getId()->value()] = $user;
        }
    }

    public function save(User $user): void
    {
        if (null !== ($existing = $this->findByEmail($user->getEmail())) && !$existing->equals($user)) {
            throw new UserAlreadyExistsException($user->getEmail());
        }

        $this->users[$user->getId()->value()] = $user;
    }

    public function delete(User $user): void
    {
        unset($this->users[$user->getId()->value()]);
    }

    public function findAll(): array
    {
        return \array_values($this->users);
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value()] ?? null;
    }

    public function findByEmail(UserEmail $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail()->equals($email)) {
                return $user;
            }
        }

        return null;
    }
}
