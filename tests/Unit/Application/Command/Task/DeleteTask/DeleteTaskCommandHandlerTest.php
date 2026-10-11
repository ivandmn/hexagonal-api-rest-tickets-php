<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\Task\DeleteTask;

use App\Application\Command\Task\DeleteTask\DeleteTaskCommand;
use App\Application\Command\Task\DeleteTask\DeleteTaskCommandHandler;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

final class DeleteTaskCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    public function test_deletes_a_pending_task_and_logs(): void
    {
        $task   = Mother::task(title: 'Deletable Task');
        $tasks  = new InMemoryTaskRepository($task);
        $logger = new SpyLogger();

        new DeleteTaskCommandHandler($tasks, $logger)(new DeleteTaskCommand($task->getId()->value()));

        $this->assertSame([], $tasks->all());
        $this->assertTrue($logger->has('info', 'Task deleted', ['task_id' => $task->getId()->value(), 'task_title' => 'Deletable Task']));
    }

    public function test_fails_when_the_task_does_not_exist(): void
    {
        $taskId  = TaskId::new()->value();
        $handler = new DeleteTaskCommandHandler(new InMemoryTaskRepository(), new SpyLogger());

        $this->assertDomainError(TaskNotFoundException::ERROR_CODE, static fn () => $handler(new DeleteTaskCommand($taskId)), ['task_id' => $taskId]);
    }

    public function test_refuses_a_task_that_is_not_pending(): void
    {
        $task    = Mother::task(status: TaskStatus::IN_PROGRESS);
        $tasks   = new InMemoryTaskRepository($task);
        $handler = new DeleteTaskCommandHandler($tasks, new SpyLogger());

        $this->assertDomainError(TaskDeletionNotAllowedException::ERROR_CODE, static fn () => $handler(new DeleteTaskCommand($task->getId()->value())), ['task_id' => $task->getId()->value(), 'status' => 'in_progress']);
        $this->assertCount(1, $tasks->all());
    }
}
