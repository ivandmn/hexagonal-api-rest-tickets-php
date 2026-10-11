<?php

declare(strict_types=1);

namespace App\Application\Query\ListUsers;

use App\Domain\Model\User;

final readonly class ListUsersResponse implements \JsonSerializable, \Countable, \IteratorAggregate
{
    public function __construct(
        private array $users
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => \array_map(
                static fn (User $user): array => [
                    'id'        => $user->getId()->value(),
                    'email'     => $user->getEmail()->value(),
                    'name'      => $user->getName()->value(),
                    'createdAt' => $user->getCreatedAt()->format(\DATE_ATOM),
                ],
                $this->users
            ),
            'meta' => [
                'total' => $this->count(),
                'page'  => 1,
            ],
        ];
    }

    public function count(): int
    {
        return \count($this->users);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->users);
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function isEmpty(): bool
    {
        return [] === $this->users;
    }
}
