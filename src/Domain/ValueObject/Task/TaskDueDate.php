<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Task;

use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\DateTime;

/**
 * When a task is due. Pure value: format and comparisons only. Whether a due date may be in the
 * past depends on *when* it is set, so that rule needs the current time and is enforced where a due
 * date is set (Task::create, Task::changeDueDate) with the injected clock. A stored due date can
 * therefore be loaded back even once it has passed.
 */
final readonly class TaskDueDate extends DateTime
{
    /**
     * @throws TaskDueDateInPastException
     */
    public function ensureNotBefore(\DateTimeImmutable $now): void
    {
        if ($this->value() < $now) {
            throw new TaskDueDateInPastException($this->value());
        }
    }
}
