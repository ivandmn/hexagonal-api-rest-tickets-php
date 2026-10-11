<?php

declare(strict_types=1);

namespace App\Application\Command\Task\UpdateTask;

use App\Application\Command\CommandInterface;
use App\Application\Validation\Field;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;

/**
 * Partial update: null fields stay unchanged.
 */
final readonly class UpdateTaskCommand implements CommandInterface
{
    private TaskId $taskId;
    private ?TaskTitle $title;
    private ?TaskDescription $description;
    private ?TaskStatus $status;
    private ?TaskPriority $priority;
    private ?TaskDueDate $dueDate;

    public function __construct(
        string $taskId,
        ?string $title,
        ?string $description,
        ?string $status,
        ?string $priority,
        ?string $dueDate,
        // null fields mean "leave unchanged", so removing the due date needs an explicit flag
        private bool $clearDueDate = false,
    ) {
        $this->taskId      = Field::of('taskId', static fn (): TaskId => TaskId::fromString($taskId));
        $this->title       = null !== $title ? Field::of('title', static fn (): TaskTitle => TaskTitle::fromString($title)) : null;
        $this->description = null !== $description ? Field::of('description', static fn (): TaskDescription => TaskDescription::fromString($description)) : null;
        $this->status      = null !== $status ? Field::of('status', static fn (): TaskStatus => TaskStatus::fromString($status)) : null;
        $this->priority    = null !== $priority ? Field::of('priority', static fn (): TaskPriority => TaskPriority::fromString($priority)) : null;
        $this->dueDate     = null !== $dueDate ? Field::of('dueDate', static fn (): TaskDueDate => TaskDueDate::fromString($dueDate)) : null;
    }

    public function getTaskId(): TaskId
    {
        return $this->taskId;
    }

    public function getTitle(): ?TaskTitle
    {
        return $this->title;
    }

    public function getDescription(): ?TaskDescription
    {
        return $this->description;
    }

    public function getStatus(): ?TaskStatus
    {
        return $this->status;
    }

    public function getPriority(): ?TaskPriority
    {
        return $this->priority;
    }

    public function getDueDate(): ?TaskDueDate
    {
        return $this->dueDate;
    }

    public function shouldClearDueDate(): bool
    {
        return $this->clearDueDate;
    }
}
