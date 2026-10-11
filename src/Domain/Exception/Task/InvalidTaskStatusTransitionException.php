<?php

declare(strict_types=1);

namespace App\Domain\Exception\Task;

use App\Domain\Exception\DomainException;
use App\Domain\ValueObject\Task\TaskStatus;

final class InvalidTaskStatusTransitionException extends DomainException
{
    public const ERROR_CODE = 'TASK_STATUS_TRANSITION_NOT_ALLOWED';

    public function __construct(TaskStatus $from, TaskStatus $to, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('Task status transition from "%s" to "%s" is not allowed', $from->value, $to->value),
            ['from' => $from->value, 'to' => $to->value],
            0,
            $previous,
        );
    }
}
