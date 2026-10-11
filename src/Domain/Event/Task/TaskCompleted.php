<?php

declare(strict_types=1);

namespace App\Domain\Event\Task;

use App\Domain\Event\EventInterface;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;

/**
 * A task moved to "completed" (a final state: it happens at most once per task).
 */
final readonly class TaskCompleted implements EventInterface
{
    public function __construct(
        public TaskId $taskId,
        public ?UserId $assignedUserId,
        private DateTime $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'task.completed';
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function payload(): array
    {
        return [
            'task_id'          => $this->taskId->value(),
            'assigned_user_id' => $this->assignedUserId?->value(),
        ];
    }
}
