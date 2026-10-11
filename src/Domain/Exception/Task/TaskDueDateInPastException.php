<?php

declare(strict_types=1);

namespace App\Domain\Exception\Task;

use App\Domain\Exception\DomainException;

final class TaskDueDateInPastException extends DomainException
{
    public const ERROR_CODE = 'TASK_DUE_DATE_IN_PAST';

    public function __construct(\DateTimeImmutable $dueDate, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('Due date %s is in the past', $dueDate->format(\DATE_ATOM)),
            ['dueDate' => $dueDate->format(\DATE_ATOM)],
            0,
            $previous,
        );
    }
}
