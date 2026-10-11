<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateTask;

use App\Application\Command\CommandInterface;
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
    /**
     * Only the format is checked here: whether the date may be in the past depends on the task
     * (re-sending its current due date is allowed), so the TaskDueDate is built by the handler.
     */
    private ?\DateTimeImmutable $dueDate;

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
        $this->taskId      = TaskId::fromString($taskId);
        $this->title       = null !== $title ? TaskTitle::fromString($title) : null;
        $this->description = null !== $description ? TaskDescription::fromString($description) : null;
        $this->status      = null !== $status ? TaskStatus::fromString($status) : null;
        $this->priority    = null !== $priority ? TaskPriority::fromString($priority) : null;
        $this->dueDate     = null !== $dueDate ? TaskDueDate::parse($dueDate) : null;
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

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function shouldClearDueDate(): bool
    {
        return $this->clearDueDate;
    }
}
