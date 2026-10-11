<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\Uuid;

final class Task
{
    private bool $isUpdated = false;

    public function __construct(
        private readonly TaskId $id,
        private TaskTitle $title,
        private TaskDescription $description,
        private TaskStatus $status,
        private TaskPriority $priority,
        private ?User $assignedUser,
        private ?TaskDueDate $dueDate,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * New tasks start pending and unassigned; a due date, if any, cannot be in the past.
     *
     * @throws TaskDueDateInPastException
     */
    public static function create(
        TaskId $id,
        TaskTitle $title,
        TaskDescription $description,
        \DateTimeImmutable $now,
        TaskPriority $priority = TaskPriority::LOW,
        ?TaskDueDate $dueDate = null,
    ): self {
        $dueDate?->ensureNotBefore($now);

        return new self($id, $title, $description, TaskStatus::PENDING, $priority, null, $dueDate, $now, null);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): TaskTitle
    {
        return $this->title;
    }

    public function getDescription(): TaskDescription
    {
        return $this->description;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function getAssignedUser(): ?User
    {
        return $this->assignedUser;
    }

    public function getDueDate(): ?TaskDueDate
    {
        return $this->dueDate;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeTitle(TaskTitle $newTitle): void
    {
        if ($this->title->equals($newTitle)) {
            return;
        }

        $this->title = $newTitle;
        $this->markAsUpdated();
    }

    public function changeDescription(TaskDescription $newDescription): void
    {
        if ($this->description->equals($newDescription)) {
            return;
        }

        $this->description = $newDescription;
        $this->markAsUpdated();
    }

    public function changeStatus(TaskStatus $newStatus): void
    {
        if ($this->status->equals($newStatus)) {
            return;
        }

        // Covers completed tasks too: nothing can transition out of "completed".
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidTaskStatusTransitionException($this->status, $newStatus);
        }

        $this->status = $newStatus;
        $this->markAsUpdated();
    }

    public function changePriority(TaskPriority $newPriority): void
    {
        if ($this->priority->equals($newPriority)) {
            return;
        }

        $this->priority = $newPriority;
        $this->markAsUpdated();
    }

    public function assignTo(User $user): void
    {
        if ($this->assignedUser?->equals($user)) {
            return;
        }
        $this->assignedUser = $user;
        $this->markAsUpdated();
    }

    /**
     * Setting the due date the task already has is a no-op, even if it is in the past by now;
     * a new due date cannot be in the past.
     *
     * @throws TaskDueDateInPastException
     */
    public function changeDueDate(?TaskDueDate $newTaskDueDate, \DateTimeImmutable $now): void
    {
        if (null === $this->dueDate && null === $newTaskDueDate) {
            return;
        }

        if (null !== $newTaskDueDate && $this->dueDate?->equals($newTaskDueDate)) {
            return;
        }

        $newTaskDueDate?->ensureNotBefore($now);

        $this->dueDate = $newTaskDueDate;
        $this->markAsUpdated();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function canBeDeleted(): bool
    {
        return $this->isPending();
    }

    public function isUpdated(): bool
    {
        return $this->isUpdated;
    }

    private function markAsUpdated(): void
    {
        $this->isUpdated = true;
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
