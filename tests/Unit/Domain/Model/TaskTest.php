<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
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
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    use AssertsDomainError;

    public function test_create_starts_pending_and_unassigned_with_defaults(): void
    {
        $id          = TaskId::new();
        $title       = TaskTitle::fromString('Test Title');
        $description = TaskDescription::fromString('Test Description');
        $now         = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');

        $task = Task::create($id, $title, $description, $now);

        $this->assertSame($id, $task->getId());
        $this->assertSame($title, $task->getTitle());
        $this->assertSame($description, $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame(TaskPriority::LOW, $task->getPriority());
        $this->assertNull($task->getAssignedUser());
        $this->assertNull($task->getDueDate());
        $this->assertSame($now, $task->getCreatedAt());
        $this->assertNull($task->getUpdatedAt());
        $this->assertFalse($task->isUpdated());
        $this->assertTrue($task->isPending());
        $this->assertFalse($task->isCompleted());
        $this->assertTrue($task->canBeDeleted());
    }

    public function test_create_with_priority_and_due_date(): void
    {
        $now = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');
        $due = TaskDueDate::fromDateTime($now->modify('+2 days'));

        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), $now, TaskPriority::HIGH, $due);

        $this->assertSame(TaskPriority::HIGH, $task->getPriority());
        $this->assertSame($due, $task->getDueDate());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
    }

    public function test_create_rejects_due_date_in_the_past(): void
    {
        $now = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');

        $this->expectException(TaskDueDateInPastException::class);

        Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), $now, TaskPriority::LOW, TaskDueDate::fromDateTime($now->modify('-1 day')));
    }

    public function test_constructs_with_all_fields_explicit(): void
    {
        $id          = TaskId::new();
        $title       = TaskTitle::fromString('Explicit Test Title');
        $description = TaskDescription::fromString('All fields description');
        $status      = TaskStatus::IN_PROGRESS;
        $priority    = TaskPriority::HIGH;
        $user        = User::create(UserId::new(), UserEmail::fromString('test@example.com'), UserName::fromString('Test User'), new \DateTimeImmutable());
        $due         = TaskDueDate::fromDateTime(new \DateTimeImmutable('now')->modify('+2 days'));
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
        $this->assertSame($updated->getTimestamp(), $task->getUpdatedAt()?->getTimestamp());
    }

    public function test_change_title_marks_updated(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Old Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());

        $task->changeTitle(TaskTitle::fromString('New Test Title'));

        $this->assertSame('New Test Title', $task->getTitle()->value());
        $this->assertTrue($task->isUpdated());
        $this->assertNotNull($task->getUpdatedAt());
    }

    public function test_change_title_noop_when_same_value(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Same Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $task->changeTitle(TaskTitle::fromString('Same Test Title'));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_description_marks_updated(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Old Test Description'), new \DateTimeImmutable());
        $task->changeDescription(TaskDescription::fromString('New Test Description'));

        $this->assertSame('New Test Description', $task->getDescription()->value());
        $this->assertTrue($task->isUpdated());
        $this->assertNotNull($task->getUpdatedAt());
    }

    public function test_change_description_noop_when_same_value(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Same Test Description'), new \DateTimeImmutable());
        $task->changeDescription(TaskDescription::fromString('Same Test Description'));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_priority_marks_updated_and_noop_when_same(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $task->changePriority(TaskPriority::MEDIUM);

        $this->assertSame(TaskPriority::MEDIUM, $task->getPriority());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changePriority(TaskPriority::MEDIUM);

        $this->assertSame($firstUpdatedAt, $task->getUpdatedAt());
    }

    public function test_assign_to_marks_updated_and_noop_when_same_user(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $u1   = User::create(UserId::new(), UserEmail::fromString('test@example.com'), UserName::fromString('Test User'), new \DateTimeImmutable());
        $u2   = User::create(UserId::new(), UserEmail::fromString('test2@example.com'), UserName::fromString('Test User 2'), new \DateTimeImmutable());

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
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());

        $due1 = TaskDueDate::fromDateTime(new \DateTimeImmutable('now')->modify('+1 day'));
        $task->changeDueDate($due1, new \DateTimeImmutable());

        $this->assertSame($due1, $task->getDueDate());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changeDueDate($due1, new \DateTimeImmutable());

        $this->assertSame($due1, $task->getDueDate());
        $this->assertSame($firstUpdatedAt, $task->getUpdatedAt());

        $due2 = TaskDueDate::fromDateTime(new \DateTimeImmutable('now')->modify('+2 days'));
        $task->changeDueDate($due2, new \DateTimeImmutable());

        $this->assertSame($due2, $task->getDueDate());
        $this->assertNotSame($firstUpdatedAt, $task->getUpdatedAt());
    }

    public function test_change_due_date_from_non_null_to_null_marks_updated(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $due  = TaskDueDate::fromDateTime(new \DateTimeImmutable('now')->modify('+3 days'));
        $task->changeDueDate($due, new \DateTimeImmutable());

        $this->assertSame($due, $task->getDueDate());
        $this->assertTrue($task->isUpdated());
        $firstUpdatedAt = $task->getUpdatedAt();

        $task->changeDueDate(null, new \DateTimeImmutable());

        $this->assertNull($task->getDueDate());
        $this->assertNotSame($firstUpdatedAt, $task->getUpdatedAt());
        $this->assertTrue($task->isUpdated());
    }

    public function test_change_due_date_to_null_when_already_null_is_noop(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $task->changeDueDate(null, new \DateTimeImmutable());

        $this->assertNull($task->getDueDate());
        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_status_valid_transitions_mark_updated(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());

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
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());

        $this->assertDomainError(InvalidTaskStatusTransitionException::ERROR_CODE, static fn () => $task->changeStatus(TaskStatus::COMPLETED));
    }

    public function test_status_change_after_completed_throws(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
        $task->changeStatus(TaskStatus::IN_PROGRESS);
        $task->changeStatus(TaskStatus::COMPLETED);

        $this->assertDomainError(InvalidTaskStatusTransitionException::ERROR_CODE, static fn () => $task->changeStatus(TaskStatus::IN_PROGRESS));
    }

    public function test_change_status_noop_when_same_status_from_pending(): void
    {
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());

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
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());
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
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), new \DateTimeImmutable());

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

    public function test_create_accepts_a_due_date_equal_to_now(): void
    {
        $now  = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('D'), $now, TaskPriority::LOW, TaskDueDate::fromDateTime($now));

        $this->assertSame('2030-01-01T10:00:00+00:00', (string) $task->getDueDate());
        $this->assertSame($now, $task->getCreatedAt());
    }

    public function test_change_due_date_rejects_a_new_date_before_now(): void
    {
        $now  = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('D'), $now);

        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, static fn () => $task->changeDueDate(TaskDueDate::fromDateTime($now->modify('-1 second')), $now));
        $this->assertNull($task->getDueDate());
        $this->assertFalse($task->isUpdated());
    }

    public function test_change_due_date_keeping_a_date_that_has_passed_is_a_no_op(): void
    {
        // Loaded from storage: the due date was valid when set, but "now" has moved past it.
        $dueDate = TaskDueDate::fromString('2030-01-01T10:00:00+00:00');
        $task    = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('D'), TaskStatus::PENDING, TaskPriority::LOW, null, $dueDate, new \DateTimeImmutable('2029-12-01T00:00:00+00:00'), null);

        $task->changeDueDate(TaskDueDate::fromString('2030-01-01T12:00:00+02:00'), new \DateTimeImmutable('2030-02-01T00:00:00+00:00'));

        $this->assertFalse($task->isUpdated(), 'Same instant in another offset is the same due date');
    }

    public function test_clearing_a_due_date_that_has_passed_is_allowed(): void
    {
        $task = new Task(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('D'), TaskStatus::PENDING, TaskPriority::LOW, null, TaskDueDate::fromString('2030-01-01T10:00:00+00:00'), new \DateTimeImmutable('2029-12-01T00:00:00+00:00'), null);

        $task->changeDueDate(null, new \DateTimeImmutable('2030-02-01T00:00:00+00:00'));

        $this->assertNull($task->getDueDate());
        $this->assertTrue($task->isUpdated());
    }
}
