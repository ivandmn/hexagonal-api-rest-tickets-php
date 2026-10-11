<?php

declare(strict_types=1);

namespace App\Application\Query\GetTaskDetails;

use App\Application\Query\QueryInterface;
use App\Domain\ValueObject\Task\TaskId;

final readonly class GetTaskDetailsQuery implements QueryInterface
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
