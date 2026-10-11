<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Event\Task\TaskAssigned;
use App\Domain\Event\Task\TaskCompleted;
use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\Model\Task;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    use AssertsDomainError;

    private const string CREATED = '2030-01-01T10:00:00+00:00';
    private const string LATER   = '2030-01-02T08:30:00+00:00';
    private const string LATEST  = '2030-01-03T09:45:00+00:00';

    public function test_create_starts_pending_and_unassigned_with_defaults(): void
    {
        $id          = TaskId::new();
        $title       = TaskTitle::fromString('Test Title');
        $description = TaskDescription::fromString('Test Description');

        $task = Task::create($id, $title, $description, $this->at(self::CREATED));

        $this->assertSame($id, $task->getId());
        $this->assertSame($title, $task->getTitle());
        $this->assertSame($description, $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame(TaskPriority::LOW, $task->getPriority());
        $this->assertNull($task->getAssignedUser());
        $this->assertFalse($task->isAssigned());
        $this->assertNull($task->getDueDate());
        $this->assertSame(self::CREATED, (string) $task->getCreatedAt());
        $this->assertNull($task->getUpdatedAt());
        $this->assertFalse($task->isUpdated());
        $this->assertTrue($task->isPending());
        $this->assertFalse($task->isCompleted());
        $this->assertTrue($task->canBeDeleted());
        $this->assertSame([], $task->pullDomainEvents());
    }

    public function test_created_at_is_kept_in_utc_whole_seconds(): void
    {
        $now  = new \DateTimeImmutable('2030-01-01 12:00:00.987654', new \DateTimeZone('Europe/Madrid'));
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('D'), $now);

        $this->assertSame('2030-01-01T11:00:00+00:00', (string) $task->getCreatedAt());
    }

    public function test_create_with_priority_and_due_date(): void
    {
        $now = $this->at(self::CREATED);
        $due = TaskDueDate::fromDateTime($now->modify('+2 days'));

        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), $now, TaskPriority::HIGH, $due);

        $this->assertSame(TaskPriority::HIGH, $task->getPriority());
        $this->assertSame($due, $task->getDueDate());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
    }

    public function test_create_rejects_due_date_in_the_past(): void
    {
        $now = $this->at(self::CREATED);

        $this->expectException(TaskDueDateInPastException::class);

        Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), $now, TaskPriority::LOW, TaskDueDate::fromDateTime($now->modify('-1 day')));
    }

    public function test_create_accepts_a_due_date_equal_to_now(): void
    {
        $now  = $this->at(self::CREATED);
        $task = Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('D'), $now, TaskPriority::LOW, TaskDueDate::fromDateTime($now));

        $this->assertSame(self::CREATED, (string) $task->getDueDate());
    }

    public function test_constructs_with_all_fields_explicit(): void
    {
        $id          = TaskId::new();
        $title       = TaskTitle::fromString('Explicit Test Title');
        $description = TaskDescription::fromString('All fields description');
        $user        = Mother::user();
        $due         = TaskDueDate::fromString('2030-02-01T00:00:00Z');
        $created     = DateTime::fromString(self::CREATED);
        $updated     = DateTime::fromString(self::LATER);

        $task = new Task($id, $title, $description, TaskStatus::IN_PROGRESS, TaskPriority::HIGH, $user, $due, $created, $updated);

        $this->assertSame($id, $task->getId());
        $this->assertSame($title, $task->getTitle());
        $this->assertSame($description, $task->getDescription());
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertSame(TaskPriority::HIGH, $task->getPriority());
        $this->assertSame($user, $task->getAssignedUser());
        $this->assertSame($due, $task->getDueDate());
        $this->assertSame($created, $task->getCreatedAt());
        $this->assertSame($updated, $task->getUpdatedAt());
    }

    public function test_change_title_sets_updated_at_from_the_given_time(): void
    {
        $task = $this->newTask();

        $task->changeTitle(TaskTitle::fromString('New Test Title'), $this->at(self::LATER));

        $this->assertSame('New Test Title', $task->getTitle()->value());
        $this->assertTrue($task->isUpdated());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());
        $this->assertSame(self::CREATED, (string) $task->getCreatedAt(), 'createdAt never changes');
    }

    public function test_change_title_noop_when_same_value(): void
    {
        $task = $this->newTask();

        $task->changeTitle(TaskTitle::fromString('Test Title'), $this->at(self::LATER));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_description_sets_updated_at(): void
    {
        $task = $this->newTask();

        $task->changeDescription(TaskDescription::fromString('New Test Description'), $this->at(self::LATER));

        $this->assertSame('New Test Description', $task->getDescription()->value());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());
    }

    public function test_change_description_noop_when_same_value(): void
    {
        $task = $this->newTask();

        $task->changeDescription(TaskDescription::fromString('Test Description'), $this->at(self::LATER));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_priority_sets_updated_at_and_noop_when_same(): void
    {
        $task = $this->newTask();

        $task->changePriority(TaskPriority::MEDIUM, $this->at(self::LATER));

        $this->assertSame(TaskPriority::MEDIUM, $task->getPriority());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());

        $task->changePriority(TaskPriority::MEDIUM, $this->at(self::LATEST));

        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());
    }

    public function test_assign_to_records_task_assigned(): void
    {
        $task = $this->newTask();
        $user = Mother::user();

        $task->assignTo($user, $this->at(self::LATER));

        $this->assertSame($user, $task->getAssignedUser());
        $this->assertTrue($task->isAssigned());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());

        $events = $task->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TaskAssigned::class, $events[0]);
        $this->assertSame('task.assigned', $events[0]->eventName());
        $this->assertSame(self::LATER, (string) $events[0]->occurredOn());
        $this->assertSame(
            ['task_id' => $task->getId()->value(), 'user_id' => $user->getId()->value(), 'previous_user_id' => null],
            $events[0]->payload(),
        );
        $this->assertSame([], $task->pullDomainEvents(), 'Pulling empties the list');
    }

    public function test_assign_to_the_current_assignee_is_a_no_op(): void
    {
        $user = Mother::user();
        $task = Mother::task(assignee: $user);

        $task->assignTo($user, $this->at(self::LATER));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
        $this->assertSame([], $task->pullDomainEvents());
    }

    public function test_reassigning_reports_the_previous_assignee(): void
    {
        $first  = Mother::user(email: 'first@example.com');
        $second = Mother::user(email: 'second@example.com');
        $task   = Mother::task(assignee: $first);

        $task->assignTo($second, $this->at(self::LATER));

        $this->assertSame($second, $task->getAssignedUser());
        $events = $task->pullDomainEvents();
        $this->assertInstanceOf(TaskAssigned::class, $events[0]);
        $this->assertTrue($events[0]->previousUserId?->equals($first->getId()));
    }

    public function test_unassign_clears_the_assignee(): void
    {
        $task = Mother::task(assignee: Mother::user());

        $task->unassign($this->at(self::LATER));

        $this->assertNull($task->getAssignedUser());
        $this->assertFalse($task->isAssigned());
        $this->assertTrue($task->isUpdated());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());
        $this->assertSame([], $task->pullDomainEvents(), 'Unassigning raises no event');
    }

    public function test_unassign_an_unassigned_task_is_a_no_op(): void
    {
        $task = $this->newTask();

        $task->unassign($this->at(self::LATER));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_due_date_sets_updated_at_and_noop_when_same(): void
    {
        $task = $this->newTask();
        $due1 = TaskDueDate::fromString('2030-02-01T00:00:00Z');

        $task->changeDueDate($due1, $this->at(self::LATER));

        $this->assertSame($due1, $task->getDueDate());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());

        $task->changeDueDate(TaskDueDate::fromString('2030-02-01T01:00:00+01:00'), $this->at(self::LATEST));

        $this->assertSame(self::LATER, (string) $task->getUpdatedAt(), 'Same instant in another offset is the same due date');

        $due2 = TaskDueDate::fromString('2030-03-01T00:00:00Z');
        $task->changeDueDate($due2, $this->at(self::LATEST));

        $this->assertSame($due2, $task->getDueDate());
        $this->assertSame(self::LATEST, (string) $task->getUpdatedAt());
    }

    public function test_change_due_date_to_null_clears_it(): void
    {
        $task = Mother::task(dueDate: TaskDueDate::fromString('2030-02-01T00:00:00Z'));

        $task->changeDueDate(null, $this->at(self::LATER));

        $this->assertNull($task->getDueDate());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());
    }

    public function test_change_due_date_to_null_when_already_null_is_noop(): void
    {
        $task = $this->newTask();

        $task->changeDueDate(null, $this->at(self::LATER));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_change_due_date_rejects_a_new_date_before_now(): void
    {
        $now  = $this->at(self::LATER);
        $task = $this->newTask();

        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, static fn () => $task->changeDueDate(TaskDueDate::fromDateTime($now->modify('-1 second')), $now));
        $this->assertNull($task->getDueDate());
        $this->assertFalse($task->isUpdated());
    }

    public function test_change_due_date_keeping_a_date_that_has_passed_is_a_no_op(): void
    {
        // Loaded from storage: the due date was valid when set, but "now" has moved past it.
        $task = Mother::task(dueDate: TaskDueDate::fromString('2030-01-01T10:00:00+00:00'));

        $task->changeDueDate(TaskDueDate::fromString('2030-01-01T12:00:00+02:00'), $this->at('2030-02-01T00:00:00+00:00'));

        $this->assertFalse($task->isUpdated());
    }

    public function test_clearing_a_due_date_that_has_passed_is_allowed(): void
    {
        $task = Mother::task(dueDate: TaskDueDate::fromString('2030-01-01T10:00:00+00:00'));

        $task->changeDueDate(null, $this->at('2030-02-01T00:00:00+00:00'));

        $this->assertNull($task->getDueDate());
        $this->assertTrue($task->isUpdated());
    }

    public function test_status_valid_transitions_set_updated_at(): void
    {
        $task = $this->newTask();

        $task->changeStatus(TaskStatus::IN_PROGRESS, $this->at(self::LATER));

        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertSame(self::LATER, (string) $task->getUpdatedAt());
        $this->assertSame([], $task->pullDomainEvents(), 'Only completing a task is an event');

        $task->changeStatus(TaskStatus::COMPLETED, $this->at(self::LATEST));

        $this->assertTrue($task->isCompleted());
        $this->assertSame(self::LATEST, (string) $task->getUpdatedAt());
    }

    public function test_completing_records_task_completed(): void
    {
        $user = Mother::user();
        $task = Mother::task(status: TaskStatus::IN_PROGRESS, assignee: $user);

        $task->changeStatus(TaskStatus::COMPLETED, $this->at(self::LATER));

        $events = $task->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TaskCompleted::class, $events[0]);
        $this->assertSame('task.completed', $events[0]->eventName());
        $this->assertSame(self::LATER, (string) $events[0]->occurredOn());
        $this->assertSame(['task_id' => $task->getId()->value(), 'assigned_user_id' => $user->getId()->value()], $events[0]->payload());
    }

    public function test_completing_an_unassigned_task_has_no_assignee_in_the_event(): void
    {
        $task = Mother::task(status: TaskStatus::IN_PROGRESS);

        $task->changeStatus(TaskStatus::COMPLETED, $this->at(self::LATER));

        $events = $task->pullDomainEvents();
        $this->assertInstanceOf(TaskCompleted::class, $events[0]);
        $this->assertNull($events[0]->assignedUserId);
    }

    public function test_status_invalid_transition_from_pending_to_completed_throws(): void
    {
        $task = $this->newTask();

        $now  = $this->at(self::LATER);

        $this->assertDomainError(InvalidTaskStatusTransitionException::ERROR_CODE, static fn () => $task->changeStatus(TaskStatus::COMPLETED, $now));
        $this->assertSame([], $task->pullDomainEvents());
    }

    public function test_status_change_after_completed_throws(): void
    {
        $task = Mother::task(status: TaskStatus::COMPLETED);

        $now  = $this->at(self::LATER);

        $this->assertDomainError(InvalidTaskStatusTransitionException::ERROR_CODE, static fn () => $task->changeStatus(TaskStatus::IN_PROGRESS, $now));
    }

    public function test_change_status_noop_when_same_status(): void
    {
        $task = Mother::task(status: TaskStatus::IN_PROGRESS);

        $task->changeStatus(TaskStatus::IN_PROGRESS, $this->at(self::LATER));

        $this->assertFalse($task->isUpdated());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_is_pending_completed_and_can_be_deleted_flags(): void
    {
        $task = $this->newTask();

        $this->assertTrue($task->isPending());
        $this->assertTrue($task->canBeDeleted());
        $this->assertFalse($task->isCompleted());

        $task->changeStatus(TaskStatus::IN_PROGRESS, $this->at(self::LATER));

        $this->assertFalse($task->isPending());
        $this->assertFalse($task->canBeDeleted());
        $this->assertFalse($task->isCompleted());

        $task->changeStatus(TaskStatus::COMPLETED, $this->at(self::LATEST));

        $this->assertFalse($task->isPending());
        $this->assertFalse($task->canBeDeleted());
        $this->assertTrue($task->isCompleted());
    }

    private function newTask(): Task
    {
        return Task::create(TaskId::new(), TaskTitle::fromString('Test Title'), TaskDescription::fromString('Test Description'), $this->at(self::CREATED));
    }

    private function at(string $instant): \DateTimeImmutable
    {
        return new \DateTimeImmutable($instant);
    }
}
