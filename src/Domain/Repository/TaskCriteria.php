<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;

/**
 * Filters for searching tasks; a null filter matches everything. Filtering by due date leaves
 * out the tasks without one.
 */
final readonly class TaskCriteria
{
    /**
     * @param DateTime|null $dueFrom due on or after this instant
     * @param DateTime|null $dueTo   due on or before this instant
     */
    public function __construct(
        public ?TaskStatus $status = null,
        public ?TaskPriority $priority = null,
        public ?UserId $assignedUserId = null,
        public ?DateTime $dueFrom = null,
        public ?DateTime $dueTo = null,
    ) {
    }
}
