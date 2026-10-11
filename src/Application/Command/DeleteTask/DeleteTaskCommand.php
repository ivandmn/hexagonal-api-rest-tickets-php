<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteTask;

use App\Application\Command\CommandInterface;
use App\Domain\ValueObject\Task\TaskId;

final readonly class DeleteTaskCommand implements CommandInterface
{
    private TaskId $taskId;

    public function __construct(string $taskId)
    {
        $this->taskId = TaskId::fromString($taskId);
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }
}
