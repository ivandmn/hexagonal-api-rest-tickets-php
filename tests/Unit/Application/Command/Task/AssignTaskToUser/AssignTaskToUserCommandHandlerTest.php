<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\Task\AssignTaskToUser;

use App\Application\Command\Task\AssignTaskToUser\AssignTaskToUserCommand;
use App\Application\Command\Task\AssignTaskToUser\AssignTaskToUserCommandHandler;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

final class AssignTaskToUserCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new SpyLogger();
    }

    public function test_assigns_the_task_persists_and_logs(): void
    {
        $task  = Mother::task();
        $user  = Mother::user();
        $tasks = new InMemoryTaskRepository($task);

        $this->handler($tasks, new InMemoryUserRepository($user))(new AssignTaskToUserCommand($task->getId()->value(), $user->getId()->value()));

        $this->assertTrue($task->getAssignedUser()?->equals($user));
        $this->assertSame(1, $tasks->updates);
        $this->assertTrue($this->logger->has('info', 'Task assigned to user', ['task_id' => $task->getId()->value(), 'user_id' => $user->getId()->value()]));
    }

    public function test_assigning_to_the_current_assignee_changes_nothing(): void
    {
        $user  = Mother::user();
        $task  = Mother::task(assignee: $user);
        $tasks = new InMemoryTaskRepository($task);

        $this->handler($tasks, new InMemoryUserRepository($user))(new AssignTaskToUserCommand($task->getId()->value(), $user->getId()->value()));

        $this->assertFalse($task->isUpdated());
        $this->assertSame(0, $tasks->updates);
        $this->assertTrue($this->logger->has('info', 'Task already assigned to this user'));
    }

    public function test_fails_when_the_task_does_not_exist(): void
    {
        $taskId  = TaskId::new()->value();
        $handler = $this->handler(new InMemoryTaskRepository(), new InMemoryUserRepository(Mother::user()));

        $this->assertDomainError(TaskNotFoundException::ERROR_CODE, static fn () => $handler(new AssignTaskToUserCommand($taskId, UserId::new()->value())), ['task_id' => $taskId]);
        $this->assertSame([], $this->logger->records);
    }

    public function test_fails_when_the_user_does_not_exist(): void
    {
        $task    = Mother::task();
        $userId  = UserId::new()->value();
        $tasks   = new InMemoryTaskRepository($task);
        $handler = $this->handler($tasks, new InMemoryUserRepository());

        $this->assertDomainError(UserNotFoundException::ERROR_CODE, static fn () => $handler(new AssignTaskToUserCommand($task->getId()->value(), $userId)), ['user_id' => $userId]);
        $this->assertNull($task->getAssignedUser());
        $this->assertSame(0, $tasks->updates);
    }

    private function handler(InMemoryTaskRepository $tasks, InMemoryUserRepository $users): AssignTaskToUserCommandHandler
    {
        return new AssignTaskToUserCommandHandler($tasks, $users, $this->logger);
    }
}
