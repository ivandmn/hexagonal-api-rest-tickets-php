<?php

declare(strict_types=1);

namespace App\Application\Command\AssignTaskToUser;

use App\Domain\Model\Task;
use App\Domain\Model\User;

final readonly class AssignTaskToUserResponse implements \JsonSerializable
{
    public function __construct(
        private Task $task,
        private User $user,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'message' => \sprintf(
                'Task "%s" has been assigned to "%s"',
                $this->task->getTitle()->value(),
                $this->user->getEmail()->value()
            ),
            'task_id' => $this->task->getId()->value(),
            'user_id' => $this->user->getId()->value(),
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
