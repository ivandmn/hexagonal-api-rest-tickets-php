<?php

declare(strict_types=1);

namespace App\Domain\Exception\Task;

use App\Domain\Exception\DomainException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskStatus;

final class TaskDeletionNotAllowedException extends DomainException
{
    public const ERROR_CODE = 'TASK_DELETION_NOT_ALLOWED';

    public function __construct(TaskId $taskId, TaskStatus $status, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('Task %s cannot be deleted while "%s"', $taskId->value(), $status->value),
            ['task_id' => $taskId->value(), 'status' => $status->value],
            0,
            $previous,
        );
    }
}
