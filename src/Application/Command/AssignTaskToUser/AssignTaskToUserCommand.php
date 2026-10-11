<?php

declare(strict_types=1);

namespace App\Application\Command\AssignTaskToUser;

use App\Application\Command\CommandInterface;
use App\Application\Validation\Field;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;

final readonly class AssignTaskToUserCommand implements CommandInterface
{
    private TaskId $taskId;
    private UserId $userId;

    public function __construct(string $taskId, string $userId)
    {
        $this->taskId = Field::of('taskId', static fn (): TaskId => TaskId::fromString($taskId));
        $this->userId = Field::of('userId', static fn (): UserId => UserId::fromString($userId));
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }
}
