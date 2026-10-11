<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\Task\TaskAssigned;
use App\Domain\Event\Task\TaskCompleted;
use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;

/**
 * Every change takes the current time ($now, from the injected clock) and becomes updatedAt.
 * Changing something to the value it already has is a no-op: updatedAt and the events stay as
 * they were.
 */
final class Task extends AggregateRoot
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
        private readonly DateTime $createdAt,
        private ?DateTime $updatedAt,
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

        return new self($id, $title, $description, TaskStatus::PENDING, $priority, null, $dueDate, DateTime::fromDateTime($now), null);
    }

    public function getId(): TaskId
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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function changeTitle(TaskTitle $newTitle, \DateTimeImmutable $now): void
    {
        if ($this->title->equals($newTitle)) {
            return;
        }

        $this->title = $newTitle;
        $this->markAsUpdated($now);
    }

    public function changeDescription(TaskDescription $newDescription, \DateTimeImmutable $now): void
    {
        if ($this->description->equals($newDescription)) {
            return;
        }

        $this->description = $newDescription;
        $this->markAsUpdated($now);
    }

    /**
     * Records TaskCompleted when the task reaches "completed".
     *
     * @throws InvalidTaskStatusTransitionException
     */
    public function changeStatus(TaskStatus $newStatus, \DateTimeImmutable $now): void
    {
        if ($this->status->equals($newStatus)) {
            return;
        }

        // Covers completed tasks too: nothing can transition out of "completed".
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new InvalidTaskStatusTransitionException($this->status, $newStatus);
        }

        $this->status = $newStatus;
        $updatedAt    = $this->markAsUpdated($now);

        if ($newStatus->isCompleted()) {
            $this->record(new TaskCompleted($this->id, $this->assignedUser?->getId(), $updatedAt));
        }
    }

    public function changePriority(TaskPriority $newPriority, \DateTimeImmutable $now): void
    {
        if ($this->priority->equals($newPriority)) {
            return;
        }

        $this->priority = $newPriority;
        $this->markAsUpdated($now);
    }

    /**
     * Records TaskAssigned, also when the task is taken over from another user.
     */
    public function assignTo(User $user, \DateTimeImmutable $now): void
    {
        if ($this->assignedUser?->equals($user)) {
            return;
        }

        $previousUser       = $this->assignedUser;
        $this->assignedUser = $user;
        $updatedAt          = $this->markAsUpdated($now);

        $this->record(new TaskAssigned($this->id, $user->getId(), $previousUser?->getId(), $updatedAt));
    }

    public function unassign(\DateTimeImmutable $now): void
    {
        if (null === $this->assignedUser) {
            return;
        }

        $this->assignedUser = null;
        $this->markAsUpdated($now);
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
        $this->markAsUpdated($now);
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isAssigned(): bool
    {
        return null !== $this->assignedUser;
    }

    public function canBeDeleted(): bool
    {
        return $this->isPending();
    }

    public function isUpdated(): bool
    {
        return $this->isUpdated;
    }

    private function markAsUpdated(\DateTimeImmutable $now): DateTime
    {
        $this->isUpdated = true;

        return $this->updatedAt = DateTime::fromDateTime($now);
    }
}
