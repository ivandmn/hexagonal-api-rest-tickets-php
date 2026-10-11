<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;

/**
 * Object mother: valid aggregates with sensible defaults, overridable per test.
 */
final class Mother
{
    public static function task(
        ?TaskId $id = null,
        string $title = 'Some task title',
        TaskStatus $status = TaskStatus::PENDING,
        TaskPriority $priority = TaskPriority::LOW,
        ?User $assignee = null,
        ?TaskDueDate $dueDate = null,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
    ): Task {
        return new Task($id ?? TaskId::new(), TaskTitle::fromString($title), TaskDescription::fromString('Some description'), $status, $priority, $assignee, $dueDate, $createdAt, null);
    }

    public static function user(?UserId $id = null, string $email = 'jane.doe@example.com', string $name = 'Jane Doe'): User
    {
        return new User($id ?? UserId::new(), UserEmail::fromString($email), UserName::fromString($name), new \DateTimeImmutable('2026-01-01T10:00:00+00:00'), null);
    }
}
