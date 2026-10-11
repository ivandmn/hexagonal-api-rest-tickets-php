<?php

declare(strict_types=1);

namespace App\Application\Query\ListTasks;

use App\Domain\Model\Task;

final readonly class ListTasksResponse implements \JsonSerializable, \Countable, \IteratorAggregate
{
    /**
     * @param list<Task> $tasks the current page
     * @param int        $total every task matching the filters, across all pages
     */
    public function __construct(
        private array $tasks,
        private int $total,
        private int $page,
        private int $limit,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'data' => \array_map(
                static fn (Task $task) => [
                    'id'          => $task->getId()->value(),
                    'title'       => $task->getTitle()->value(),
                    'description' => $task->getDescription()->value(),
                    'status'      => $task->getStatus()->value,
                    'priority'    => $task->getPriority()->value,
                    'assignedTo'  => $task->getAssignedUser() ? [
                        'id'   => $task->getAssignedUser()->getId()->value(),
                        'name' => $task->getAssignedUser()->getName()->value(),
                    ] : null,
                    'dueDate'   => $task->getDueDate()?->value()->format(\DATE_ATOM),
                    'createdAt' => $task->getCreatedAt()->format(\DATE_ATOM),
                    'updatedAt' => $task->getUpdatedAt()?->format(\DATE_ATOM),
                ],
                $this->tasks,
            ),
            'meta' => [
                'total' => $this->total,
                'page'  => $this->page,
                'limit' => $this->limit,
            ],
        ];
    }

    public function count(): int
    {
        return \count($this->tasks);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->tasks);
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function isEmpty(): bool
    {
        return [] === $this->tasks;
    }
}
