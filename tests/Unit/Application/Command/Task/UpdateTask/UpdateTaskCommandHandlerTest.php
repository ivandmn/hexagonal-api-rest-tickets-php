<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\Task\UpdateTask;

use App\Application\Command\Task\UpdateTask\UpdateTaskCommand;
use App\Application\Command\Task\UpdateTask\UpdateTaskCommandHandler;
use App\Domain\Event\Task\TaskCompleted;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Model\Task;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Double\SpyEventBus;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class UpdateTaskCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private const string NOW = '2026-06-01T12:00:00+00:00';

    private InMemoryTaskRepository $tasks;
    private SpyLogger $appLogger;
    private SpyEventBus $eventBus;

    protected function setUp(): void
    {
        $this->tasks       = new InMemoryTaskRepository();
        $this->appLogger   = new SpyLogger();
        $this->eventBus    = new SpyEventBus();
    }

    public function test_updates_the_given_fields_persists_and_logs(): void
    {
        $task = $this->stored(Mother::task(title: 'Old Title'));

        $this->update($task, title: 'New Valid Title', description: 'New Desc', status: 'in_progress', priority: 'high', dueDate: '2026-06-02T10:00:00+00:00');

        $this->assertSame('New Valid Title', $task->getTitle()->value());
        $this->assertSame('New Desc', $task->getDescription()->value());
        $this->assertSame(TaskStatus::IN_PROGRESS, $task->getStatus());
        $this->assertSame(TaskPriority::HIGH, $task->getPriority());
        $this->assertSame('2026-06-02T10:00:00+00:00', (string) $task->getDueDate());
        $this->assertSame(1, $this->tasks->updates);
        $this->assertSame(self::NOW, (string) $task->getUpdatedAt(), 'updatedAt comes from the clock');
        $this->assertTrue($this->appLogger->has('info', 'Task updated', ['task_id' => $task->getId()->value()]));
        $this->assertSame([], $this->eventBus->events, 'Moving to in_progress is not an event');
    }

    public function test_completing_a_task_dispatches_task_completed(): void
    {
        $user = Mother::user();
        $task = $this->stored(Mother::task(status: TaskStatus::IN_PROGRESS, assignee: $user));

        $this->update($task, status: 'completed');

        $this->assertCount(1, $this->eventBus->events);
        $event = $this->eventBus->ofType(TaskCompleted::class)[0];
        $this->assertTrue($event->taskId->equals($task->getId()));
        $this->assertTrue($event->assignedUserId?->equals($user->getId()));
        $this->assertSame(self::NOW, (string) $event->occurredOn());
    }

    public function test_null_fields_are_left_unchanged(): void
    {
        $task = $this->stored(Mother::task(title: 'Keep Title', priority: TaskPriority::MEDIUM));

        $this->update($task, priority: 'high');

        $this->assertSame('Keep Title', $task->getTitle()->value());
        $this->assertSame(TaskPriority::HIGH, $task->getPriority());
    }

    public function test_same_values_are_not_persisted(): void
    {
        $task = $this->stored(Mother::task(title: 'Keep Title'));

        $this->update($task, title: 'Keep Title', status: 'pending', priority: 'low');

        $this->assertSame(0, $this->tasks->updates);
        $this->assertTrue($this->appLogger->has('info', 'Task not updated (no changes detected)'));
    }

    public function test_fails_when_the_task_does_not_exist(): void
    {
        $taskId  = TaskId::new()->value();
        $handler = $this->handler();

        $this->assertDomainError(TaskNotFoundException::ERROR_CODE, static fn () => $handler(new UpdateTaskCommand($taskId, 'Some title', null, null, null, null)), ['task_id' => $taskId]);
    }

    public function test_clears_the_due_date_when_asked(): void
    {
        $task = $this->stored(Mother::task(dueDate: TaskDueDate::fromString('2026-07-01T00:00:00+00:00')));

        $this->handler()(new UpdateTaskCommand($task->getId()->value(), null, null, null, null, null, clearDueDate: true));

        $this->assertNull($task->getDueDate());
        $this->assertSame(1, $this->tasks->updates);
    }

    public function test_rejects_a_new_due_date_before_now(): void
    {
        $task = $this->stored(Mother::task());

        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, fn () => $this->update($task, dueDate: '2026-06-01T11:59:59+00:00'));
        $this->assertNull($task->getDueDate());
        $this->assertSame(0, $this->tasks->updates);
    }

    public function test_resending_a_due_date_that_has_already_passed_is_a_no_op(): void
    {
        // Stored when it was still in the future; by NOW it has passed.
        $task = $this->stored(Mother::task(dueDate: TaskDueDate::fromString('2026-05-01T10:00:00+00:00')));

        $this->update($task, dueDate: '2026-05-01T10:00:00+00:00');

        $this->assertSame('2026-05-01T10:00:00+00:00', (string) $task->getDueDate());
        $this->assertSame(0, $this->tasks->updates);
    }

    private function stored(Task $task): Task
    {
        $this->tasks->create($task);

        return $task;
    }

    private function update(Task $task, ?string $title = null, ?string $description = null, ?string $status = null, ?string $priority = null, ?string $dueDate = null): void
    {
        $this->handler()(new UpdateTaskCommand($task->getId()->value(), $title, $description, $status, $priority, $dueDate));
    }

    private function handler(): UpdateTaskCommandHandler
    {
        return new UpdateTaskCommandHandler($this->tasks, $this->eventBus, new MockClock(self::NOW), $this->appLogger);
    }
}
