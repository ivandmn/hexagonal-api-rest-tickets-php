<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;

/**
 * Filters for searching tasks; a null filter matches everything.
 */
final readonly class TaskCriteria
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?TaskPriority $priority = null,
    ) {
    }
}
