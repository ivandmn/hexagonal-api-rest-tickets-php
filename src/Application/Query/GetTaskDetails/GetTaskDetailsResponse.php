<?php

declare(strict_types=1);

namespace App\Application\Query\GetTaskDetails;

use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\ValueObject\Task\TaskDueDate;

final class GetTaskDetailsResponse implements \JsonSerializable
{
    private string $id;

    private string $title;

    private string $description;

    private string $status;

    private string $priority;

    private ?User $assignedTo;

    private ?TaskDueDate $dueDate;

    private \DateTimeImmutable $createdAt;

    private ?\DateTime $updatedAt;

    public function __construct(Task $task)
    {
        $this->id          = $task->getId()->value();
        $this->title       = $task->getTitle()->value();
        $this->description = $task->getDescription()->value();
        $this->status      = $task->getStatus()->value;
        $this->priority    = $task->getPriority()->value;
        $this->assignedTo  = $task->getAssignedUser();
        $this->dueDate     = $task->getDueDate();
        $this->createdAt   = $task->getCreatedAt();
        $this->updatedAt   = $task->getUpdatedAt();
    }

    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'priority'    => $this->priority,
            'assignedTo'  => $this->assignedTo ? [
                'id'   => $this->assignedTo->getId()->value(),
                'name' => $this->assignedTo->getName()->value(),
            ] : null,
            'dueDate'   => $this->dueDate?->value()->format(\DATE_ATOM),
            'createdAt' => $this->createdAt->format(\DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(\DATE_ATOM),
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
