<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteUser;

use App\Application\Command\CommandInterface;
use App\Application\Validation\Field;
use App\Domain\ValueObject\User\UserId;

final readonly class DeleteUserCommand implements CommandInterface
{
    private UserId $userId;

    public function __construct(string $userId)
    {
        $this->userId = Field::of('userId', static fn (): UserId => UserId::fromString($userId));
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }
}
