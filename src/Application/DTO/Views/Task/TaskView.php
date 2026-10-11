<?php

declare(strict_types=1);

namespace App\Application\DTO\Views\Task;

use App\Domain\Model\Task;

/**
 * Read model of a task, returned by the queries. Formatting (JSON, dates) is up to the caller.
 */
final readonly class TaskView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public string $status,
        public string $priority,
        public ?AssignedUserView $assignedTo,
        public ?\DateTimeImmutable $dueDate,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromTask(Task $task): self
    {
        $assignee = $task->getAssignedUser();

        return new self(
            $task->getId()->value(),
            $task->getTitle()->value(),
            $task->getDescription()->value(),
            $task->getStatus()->value,
            $task->getPriority()->value,
            null !== $assignee ? AssignedUserView::fromUser($assignee) : null,
            $task->getDueDate()?->value(),
            $task->getCreatedAt(),
            $task->getUpdatedAt(),
        );
    }
}
