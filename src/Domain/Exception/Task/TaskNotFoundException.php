<?php

declare(strict_types=1);

namespace App\Domain\Exception\Task;

use App\Domain\Exception\DomainException;
use App\Domain\ValueObject\Task\TaskId;

final class TaskNotFoundException extends DomainException
{
    public const ERROR_CODE = 'TASK_NOT_FOUND';

    public function __construct(TaskId $taskId, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('Task %s does not exist', $taskId->value()),
            ['taskId' => $taskId->value()],
            0,
            $previous,
        );
    }
}
