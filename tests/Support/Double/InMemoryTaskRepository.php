<?php

declare(strict_types=1);

namespace App\Tests\Support\Double;

use App\Domain\Model\Task;
use App\Domain\Repository\TaskCriteria;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;

/**
 * Test double: keeps tasks in memory so handler tests can assert on state.
 */
final class InMemoryTaskRepository implements TaskRepositoryInterface
{
    /** @var array<string, Task> */
    private array $tasks = [];

    public int $updates = 0;

    public function __construct(Task ...$tasks)
    {
        foreach ($tasks as $task) {
            $this->tasks[$task->getId()->value()] = $task;
        }
    }

    public function create(Task $task): void
    {
        $this->tasks[$task->getId()->value()] = $task;
    }

    public function update(Task $task): void
    {
        $this->tasks[$task->getId()->value()] = $task;
        ++$this->updates;
    }

    public function delete(Task $task): void
    {
        unset($this->tasks[$task->getId()->value()]);
    }

    public function findById(TaskId $id): ?Task
    {
        return $this->tasks[$id->value()] ?? null;
    }

    public function findByCriteria(TaskCriteria $criteria, int $page, int $maxItems): array
    {
        $matching = $this->matching($criteria);
        \usort($matching, static fn (Task $a, Task $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        return \array_slice($matching, ($page - 1) * $maxItems, $maxItems);
    }

    public function countByCriteria(TaskCriteria $criteria): int
    {
        return \count($this->matching($criteria));
    }

    public function countByUser(UserId $userId): int
    {
        return \count($this->assignedTo($userId));
    }

    public function countUnfinishedByUser(UserId $userId): int
    {
        return \count(\array_filter($this->assignedTo($userId), static fn (Task $task): bool => TaskStatus::COMPLETED !== $task->getStatus()));
    }

    /**
     * @return list<Task>
     */
    public function all(): array
    {
        return \array_values($this->tasks);
    }

    /**
     * @return list<Task>
     */
    private function matching(TaskCriteria $criteria): array
    {
        return \array_values(\array_filter(
            $this->tasks,
            static fn (Task $task): bool => (null === $criteria->status || $criteria->status === $task->getStatus())
                && (null === $criteria->priority || $criteria->priority === $task->getPriority()),
        ));
    }

    /**
     * @return list<Task>
     */
    private function assignedTo(UserId $userId): array
    {
        return \array_values(\array_filter($this->tasks, static fn (Task $task): bool => $task->getAssignedUser()?->getId()->equals($userId) ?? false));
    }
}
