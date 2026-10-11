<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Factory;

use App\Domain\Factory\TaskFactory;
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
use PHPUnit\Framework\TestCase;

final class TaskFactoryTest extends TestCase
{
    public function test_register_creates_a_valid_task(): void
    {
        $factory = new TaskFactory();

        $taskTitle        = TaskTitle::fromString('Test Task Title');
        $taskDescription  = TaskDescription::fromString('Test Task Description');
        $taskPriority     = TaskPriority::LOW;
        $dueDate          = new \DateTimeImmutable('2030-12-31');
        $taskDueDate      = TaskDueDate::fromDate($dueDate);
        $taskAssignedUser = new User(UserId::new(), UserEmail::fromString('test@example.com'), UserName::fromString('Test User'));

        $before = new \DateTimeImmutable('now');
        $task   = $factory->register($taskTitle, $taskDescription, $taskPriority, $taskDueDate, $taskAssignedUser);
        $after  = new \DateTimeImmutable('now');

        $this->assertInstanceOf(TaskId::class, $task->getId());
        $this->assertInstanceOf(TaskTitle::class, $task->getTitle());
        $this->assertInstanceOf(TaskDescription::class, $task->getDescription());
        $this->assertInstanceOf(TaskPriority::class, $task->getPriority());
        $this->assertInstanceOf(TaskDueDate::class, $task->getDueDate());
        $this->assertInstanceOf(TaskStatus::class, $task->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getCreatedAt());

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $task->getCreatedAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $task->getCreatedAt()->getTimestamp());

        $this->assertLessThanOrEqual($task->getDueDate()->value()->getTimestamp(), $task->getCreatedAt()->getTimestamp());

        $this->assertSame($taskTitle, $task->getTitle());
        $this->assertSame($taskDescription, $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame($taskPriority, $task->getPriority());
        $this->assertSame($taskAssignedUser, $task->getAssignedUser());
        $this->assertNull($task->getUpdatedAt());
        $this->assertSame($taskDueDate, $task->getDueDate());
    }

    public function test_register_creates_a_valid_task_with_default_values(): void
    {
        $factory = new TaskFactory();

        $taskTitle       = TaskTitle::fromString('Test Task Title');
        $taskDescription = TaskDescription::fromString('Test Task Description');

        $before = new \DateTimeImmutable('now');
        $task   = $factory->register($taskTitle, $taskDescription);
        $after  = new \DateTimeImmutable('now');

        $this->assertInstanceOf(TaskId::class, $task->getId());
        $this->assertInstanceOf(TaskTitle::class, $task->getTitle());
        $this->assertInstanceOf(TaskDescription::class, $task->getDescription());
        $this->assertInstanceOf(TaskPriority::class, $task->getPriority());
        $this->assertInstanceOf(TaskStatus::class, $task->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getCreatedAt());

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $task->getCreatedAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $task->getCreatedAt()->getTimestamp());

        $this->assertSame($taskTitle, $task->getTitle());
        $this->assertSame($taskDescription, $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame(TaskPriority::LOW, $task->getPriority());
        $this->assertNull($task->getAssignedUser());
        $this->assertNull($task->getUpdatedAt());
        $this->assertNull($task->getDueDate());
    }
}
