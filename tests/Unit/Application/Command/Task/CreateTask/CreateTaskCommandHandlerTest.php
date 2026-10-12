<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\Task\CreateTask;

use App\Application\Command\Task\CreateTask\CreateTaskCommand;
use App\Application\Command\Task\CreateTask\CreateTaskCommandHandler;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateTaskCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private const string ID  = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';
    private const string NOW = '2026-06-01T12:00:00+00:00';

    private InMemoryTaskRepository $tasks;
    private SpyLogger $appLogger;
    private CreateTaskCommandHandler $handler;

    protected function setUp(): void
    {
        $this->tasks   = new InMemoryTaskRepository();
        $this->appLogger  = new SpyLogger();
        $this->handler = new CreateTaskCommandHandler($this->tasks, new MockClock(self::NOW), $this->appLogger);
    }

    public function test_creates_a_pending_unassigned_task_with_the_given_id(): void
    {
        ($this->handler)(new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'medium', '2026-07-01T09:00:00+00:00'));

        $task = $this->tasks->findById(TaskId::fromString(self::ID));
        $this->assertNotNull($task);
        $this->assertSame('Test Title', $task->getTitle()->value());
        $this->assertSame('Test Description', $task->getDescription()->value());
        $this->assertSame(TaskPriority::MEDIUM, $task->getPriority());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertNull($task->getAssignedUser());
        $this->assertSame('2026-07-01T09:00:00+00:00', (string) $task->getDueDate());
        $this->assertNull($task->getUpdatedAt());
    }

    public function test_created_at_comes_from_the_clock(): void
    {
        ($this->handler)(new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'low', null));

        $task = $this->tasks->findById(TaskId::fromString(self::ID));
        $this->assertSame(self::NOW, $task?->getCreatedAt()->format(\DATE_ATOM));
    }

    public function test_logs_the_creation(): void
    {
        ($this->handler)(new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'low', null));

        $this->assertTrue($this->appLogger->has('info', 'Task created', ['task_id' => self::ID, 'task_title' => 'Test Title']));
    }

    public function test_rejects_a_due_date_before_now_and_persists_nothing(): void
    {
        $this->assertDomainError(
            TaskDueDateInPastException::ERROR_CODE,
            fn () => ($this->handler)(new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'low', '2026-05-31T23:59:59+00:00')),
        );

        $this->assertSame([], $this->tasks->all());
    }

    public function test_accepts_a_due_date_equal_to_now(): void
    {
        ($this->handler)(new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'low', self::NOW));

        $this->assertCount(1, $this->tasks->all());
    }
}
