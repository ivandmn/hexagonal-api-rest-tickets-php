<?php

declare(strict_types=1);

namespace App\Application\Command\AssignTaskToUser;

use App\Application\Command\CommandInterface;

final readonly class AssignTaskToUserCommand implements CommandInterface
{
    public function __construct(
        private string $taskId,
        private string $userId,
    ) {
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}
