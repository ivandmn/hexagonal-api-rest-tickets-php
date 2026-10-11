<?php

declare(strict_types=1);

namespace App\Domain\Event\Task;

use App\Domain\Event\EventInterface;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;

/**
 * A task was assigned to a user, for the first time or taken over from another one.
 */
final readonly class TaskAssigned implements EventInterface
{
    public function __construct(
        public TaskId $taskId,
        public UserId $userId,
        public ?UserId $previousUserId,
        private DateTime $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'task.assigned';
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function payload(): array
    {
        return [
            'task_id'          => $this->taskId->value(),
            'user_id'          => $this->userId->value(),
            'previous_user_id' => $this->previousUserId?->value(),
        ];
    }
}
