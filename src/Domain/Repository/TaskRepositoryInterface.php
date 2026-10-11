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

    public function findAll(): array;

    public function findById(TaskId $id): ?Task;

    public function findByUserId(UserId $userId): array;

    /**
     * One page of the tasks matching the filters (all tasks when empty), newest first.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<Task>
     */
    public function findByFilters(array $filters, int $page, int $maxItems): array;

    /**
     * @param array<string, mixed> $filters
     */
    public function countByFilters(array $filters): int;
}
