<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

use App\Domain\Model\User;

final class CreateUserResponse implements \JsonSerializable
{
    private string $id;

    private string $email;

    private string $name;

    private string $createdAt;

    public function __construct(User $user)
    {
        $this->id        = $user->getId()->value();
        $this->email     = $user->getEmail()->value();
        $this->name      = $user->getName()->value();
        $this->createdAt = $user->getCreatedAt()->format(\DATE_ATOM);
    }

    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->id,
            'email'     => $this->email,
            'name'      => $this->name,
            'createdAt' => $this->createdAt,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
