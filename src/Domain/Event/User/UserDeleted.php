<?php

declare(strict_types=1);

namespace App\Domain\Event\User;

use App\Domain\Event\EventInterface;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\User\UserId;

/**
 * A user was deleted, together with their (completed) tasks.
 */
final readonly class UserDeleted implements EventInterface
{
    public function __construct(
        public UserId $userId,
        public int $deletedTasks,
        private DateTime $occurredOn,
    ) {
    }

    public function eventName(): string
    {
        return 'user.deleted';
    }

    public function occurredOn(): DateTime
    {
        return $this->occurredOn;
    }

    public function payload(): array
    {
        return [
            'user_id'       => $this->userId->value(),
            'deleted_tasks' => $this->deletedTasks,
        ];
    }
}
