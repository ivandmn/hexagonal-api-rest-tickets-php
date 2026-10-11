<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
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
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function test_constructs_with_defaults_and_getters(): void
    {
        $id          = TaskId::new();
        $title       = TaskTitle::fromString('Test Title');
        $description = TaskDescription::fromString('Test Description');

        $before = new \DateTimeImmutable('now');
        $task   = new Task($id, $title, $description);
        $after  = new \DateTimeImmutable('now');

        $this->assertSame($id, $task->getId());
        $this->assertSame($title, $task->getTitle());
        $this->assertSame($description, $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame(TaskPriority::LOW, $task->getPriority());
        $this->assertNull($task->getAssignedUser());
        $this->assertNull($task->getDueDate());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getCreatedAt());
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $task->getCreatedAt()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $task->getCreatedAt()->getTimestamp());
        $this->assertNull($task->getUpdatedAt());
        $this->assertFalse($task->isUpdated());
        $this->assertTrue($task->isPending());
        $this->assertFalse($task->isCompleted());
        $this->assertTrue($task->canBeDeleted());
    }

    public function test_constructs_with_all_fields_explicit(): void
    {
        $id          = TaskId::new();
        $title       = TaskTitle::fromString('Explicit Test Title');
        $description = TaskDescription::fromString('All fields description');
        $status      = TaskStatus::IN_PROGRESS;
        $priority    = TaskPriority::HIGH;
        $user        = new User(UserId::new(), UserEmail::fromString('test@example.com'), UserName::fromString('Test User'));
        $due         = TaskDueDate::fromDate(new \DateTimeImmutable('now')->modify('+2 days'));
        $created     = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');
        $updated     = new \DateTimeImmutable('2030-01-02T10:00:00+00:00');

        $task = new Task($id, $title, $description, $status, $priority, $user, $due, $created, $updated);

        $this->assertSame($id, $task->getId());
        $this->assertSame($title, $task->getTitle());
        $this->assertSame($description, $task->getDescription());
        $this->assertSame($status, $task->getStatus());
        $this->assertSame($priority, $task->getPriority());
        $this->assertSame($user, $task->getAssignedUser());
        $this->assertSame($due, $task->getDueDate());
        $this->assertSame($created->getTimestamp(), $task->getCreatedAt()->getTimestamp());
        $this->assertSame($updated->getTimestamp(), $task->getUpdatedAt()->getTimestamp());
    }

    public function test_change_title_marks_updated(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Old Test Title'), TaskDescription::fromString('Test Description'));
        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());

        $task->changeTitle(TaskTitle::fromString('New Test Title'));

        $this->assertSame('New Test Title', $task->getTitle()->value());
        $this->assertTrue($task->isUpdated());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getUpdatedAt());
    }

    public function test_change_title_noop_when_same_value(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Same Test Title'), TaskDescription::fromString('Test Description'));
        $task->changeTitle(TaskTitle::fromString('Same Test Title'));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_description_marks_updated(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Old Test Description'));
        $task->changeDescription(TaskDescription::fromString('New Test Description'));

        $this->assertSame('New Test Description', $task->getDescription()->value());
        $this->assertTrue($task->isUpdated());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getUpdatedAt());
    }

    public function test_change_description_noop_when_same_value(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Same Test Description'));
        $task->changeDescription(TaskDescription::fromString('Same Test Description'));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_priority_marks_updated_and_noop_when_same(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));
        $task->changePriority(TaskPriority::MEDIUM);

        $this->assertSame(TaskPriority::MEDIUM, $task->getPriority());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changePriority(TaskPriority::MEDIUM);

        $this->assertSame($firstUpdatedAt, $task->getUpdatedAt());
    }

    public function test_assign_to_marks_updated_and_noop_when_same_user(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));
        $u1   = new User(UserId::new(), UserEmail::fromString('test@example.com'), UserName::fromString('Test User'));
        $u2   = new User(UserId::new(), UserEmail::fromString('test2@example.com'), UserName::fromString('Test User 2'));

        $task->assignTo($u1);

        $this->assertSame($u1, $task->getAssignedUser());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->assignTo($u1);

        $this->assertSame($firstUpdatedAt, $task->getUpdatedAt());

        $task->assignTo($u2);

        $this->assertSame($u2, $task->getAssignedUser());
        $this->assertNotSame($firstUpdatedAt, $task->getUpdatedAt());
    }

    public function test_change_due_date_marks_updated_and_noop_when_same(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));

        $due1 = TaskDueDate::fromDate(new \DateTimeImmutable('now')->modify('+1 day'));
        $task->changeDueDate($due1);

        $this->assertSame($due1, $task->getDueDate());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changeDueDate($due1);

        $this->assertSame($due1, $task->getDueDate());
        $this->assertSame($firstUpdatedAt, $task->getUpdatedAt());

        $due2 = TaskDueDate::fromDate(new \DateTimeImmutable('now')->modify('+2 days'));
        $task->changeDueDate($due2);

        $this->assertSame($due2, $task->getDueDate());
        $this->assertNotSame($firstUpdatedAt, $task->getUpdatedAt());
    }

    public function test_change_due_date_from_non_null_to_null_marks_updated(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));
        $due  = TaskDueDate::fromDate(new \DateTimeImmutable('now')->modify('+3 days'));
        $task->changeDueDate($due);

        $this->assertSame($due, $task->getDueDate());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changeDueDate(null);

        $this->assertNull($task->getDueDate());
        $this->assertNotSame($firstUpdatedAt, $task->getUpdatedAt());
        $this->assertTrue($task->isUpdated());
    }

    public function test_change_due_date_to_null_when_already_null_is_noop(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));
        $task->changeDueDate(null);

        $this->assertNull($task->getDueDate());
        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_status_valid_transitions_mark_updated(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));

        $task->changeStatus(TaskStatus::IN_PROGRESS);

        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertTrue($task->isUpdated());
        $this->assertFalse($task->canBeDeleted());

        $prevUpdated = $task->getUpdatedAt();
        $task->changeStatus(TaskStatus::COMPLETED);

        $this->assertSame(TaskStatus::COMPLETED, $task->getStatus());
        $this->assertTrue($task->isCompleted());
        $this->assertNotSame($prevUpdated, $task->getUpdatedAt());
    }

    public function test_status_invalid_transition_from_pending_to_completed_throws(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));

        $this->expectException(InvalidTaskStatusTransitionException::class);
        $task->changeStatus(TaskStatus::COMPLETED);
    }

    public function test_status_change_after_completed_throws(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));
        $task->changeStatus(TaskStatus::IN_PROGRESS);
        $task->changeStatus(TaskStatus::COMPLETED);

        $this->expectException(InvalidTaskStatusTransitionException::class);
        $task->changeStatus(TaskStatus::IN_PROGRESS);
    }

    public function test_change_status_noop_when_same_status_from_pending(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));

        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());

        $task->changeStatus(TaskStatus::PENDING);

        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_status_noop_when_same_status_from_in_progress(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));
        $task->changeStatus(TaskStatus::IN_PROGRESS);

        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changeStatus(TaskStatus::IN_PROGRESS);

        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertSame($firstUpdatedAt, $task->getUpdatedAt());
    }

    public function test_is_pending_completed_and_can_be_deleted_flags(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'));

        $this->assertTrue($task->isPending());
        $this->assertTrue($task->canBeDeleted());
        $this->assertFalse($task->isCompleted());

        $task->changeStatus(TaskStatus::IN_PROGRESS);

        $this->assertFalse($task->isPending());
        $this->assertFalse($task->canBeDeleted());
        $this->assertFalse($task->isCompleted());

        $task->changeStatus(TaskStatus::COMPLETED);

        $this->assertFalse($task->isPending());
        $this->assertFalse($task->canBeDeleted());
        $this->assertTrue($task->isCompleted());
    }
}
