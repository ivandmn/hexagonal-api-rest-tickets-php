<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Task;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;

interface TaskRepositoryInterface
{
    public function create(Task $task): void;

    public function update(Task $task): void;

    public function delete(Task $task): void;

    public function findById(TaskId $id): ?Task;

    /**
     * One page of the tasks matching the criteria, newest first.
     *
     * @return list<Task>
     */
    public function findByCriteria(TaskCriteria $criteria, int $page, int $maxItems): array;

    /**
     * Every task matching the criteria, across all pages.
     */
    public function countByCriteria(TaskCriteria $criteria): int;

    /**
     * Tasks assigned to the user, whatever their status.
     */
    public function countByUser(UserId $userId): int;

    /**
     * Tasks assigned to the user that are not completed yet (pending or in progress).
     */
    public function countUnfinishedByUser(UserId $userId): int;
}
