<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\Task;

use App\Domain\ValueObject\Task\TaskDueDate;
use App\Infrastructure\Persistence\Doctrine\Type\DateTimeValueType;

/**
 * @extends DateTimeValueType<TaskDueDate>
 */
final class TaskDueDateType extends DateTimeValueType
{
    public const NAME = 'task_due_date';

    protected function valueObjectClass(): string
    {
        return TaskDueDate::class;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
