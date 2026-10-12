<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\Task\UnassignTask;

use App\Application\Command\Task\UnassignTask\UnassignTaskCommand;
use App\Application\Command\Task\UnassignTask\UnassignTaskCommandHandler;
use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class UnassignTaskCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private const string NOW = '2030-01-01T10:00:00+00:00';

    private SpyLogger $appLogger;

    protected function setUp(): void
    {
        $this->appLogger = new SpyLogger();
    }

    public function test_unassigns_the_task_persists_and_logs(): void
    {
        $user  = Mother::user();
        $task  = Mother::task(assignee: $user);
        $tasks = new InMemoryTaskRepository($task);

        $view = $this->handler($tasks)(new UnassignTaskCommand($task->getId()->value()));

        $this->assertNull($task->getAssignedUser());
        $this->assertNull($view->assignedTo);
        $this->assertSame(self::NOW, (string) $task->getUpdatedAt());
        $this->assertSame(1, $tasks->updates);
        $this->assertTrue($this->appLogger->has('info', 'Task unassigned', ['task_id' => $task->getId()->value(), 'previous_user_id' => $user->getId()->value()]));
    }

    public function test_an_unassigned_task_is_left_as_it_is(): void
    {
        $task  = Mother::task();
        $tasks = new InMemoryTaskRepository($task);

        $this->handler($tasks)(new UnassignTaskCommand($task->getId()->value()));

        $this->assertFalse($task->isUpdated());
        $this->assertSame(0, $tasks->updates);
        $this->assertTrue($this->appLogger->has('info', 'Task already unassigned'));
    }

    public function test_fails_when_the_task_does_not_exist(): void
    {
        $taskId  = TaskId::new()->value();
        $handler = $this->handler(new InMemoryTaskRepository());

        $this->assertDomainError(TaskNotFoundException::ERROR_CODE, static fn () => $handler(new UnassignTaskCommand($taskId)), ['task_id' => $taskId]);
    }

    public function test_the_command_rejects_an_invalid_id(): void
    {
        $this->assertFieldError('taskId', InvalidValueException::INVALID_FORMAT, static fn () => new UnassignTaskCommand('nope'));
    }

    private function handler(InMemoryTaskRepository $tasks): UnassignTaskCommandHandler
    {
        return new UnassignTaskCommandHandler($tasks, new MockClock(self::NOW), $this->appLogger);
    }
}
