<?php

declare(strict_types=1);

namespace App\Application\Command\Task\DeleteTask;

use App\Application\Command\CommandInterface;
use App\Application\Validation\Field;
use App\Domain\ValueObject\Task\TaskId;

final readonly class DeleteTaskCommand implements CommandInterface
{
    private TaskId $taskId;

    public function __construct(string $taskId)
    {
        $this->taskId = Field::of('taskId', static fn (): TaskId => TaskId::fromString($taskId));
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }
}
