<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\Task\AssignTaskToUser;

use App\Application\Command\Task\AssignTaskToUser\AssignTaskToUserCommand;
use App\Application\Command\Task\AssignTaskToUser\AssignTaskToUserCommandHandler;
use App\Domain\Event\Task\TaskAssigned;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Double\SpyEventBus;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AssignTaskToUserCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private const NOW = '2030-01-01T10:00:00+00:00';

    private SpyLogger $appLogger;
    private SpyEventBus $eventBus;

    protected function setUp(): void
    {
        $this->appLogger   = new SpyLogger();
        $this->eventBus = new SpyEventBus();
    }

    public function test_assigns_the_task_persists_and_dispatches_task_assigned(): void
    {
        $task  = Mother::task();
        $user  = Mother::user();
        $tasks = new InMemoryTaskRepository($task);

        $this->handler($tasks, new InMemoryUserRepository($user))(new AssignTaskToUserCommand($task->getId()->value(), $user->getId()->value()));

        $this->assertTrue($task->getAssignedUser()?->equals($user));
        $this->assertSame(1, $tasks->updates);
        $this->assertSame(self::NOW, (string) $task->getUpdatedAt());

        $this->assertCount(1, $this->eventBus->events);
        $event = $this->eventBus->ofType(TaskAssigned::class)[0];
        $this->assertTrue($event->taskId->equals($task->getId()));
        $this->assertTrue($event->userId->equals($user->getId()));
        $this->assertNull($event->previousUserId);
        $this->assertSame(self::NOW, (string) $event->occurredOn());
        $this->assertSame([], $task->pullDomainEvents(), 'Events are dispatched once');
    }

    public function test_taking_over_a_task_reports_the_previous_assignee(): void
    {
        $previous = Mother::user(email: 'previous@example.com');
        $user     = Mother::user();
        $task     = Mother::task(assignee: $previous);

        $this->handler(new InMemoryTaskRepository($task), new InMemoryUserRepository($previous, $user))(new AssignTaskToUserCommand($task->getId()->value(), $user->getId()->value()));

        $this->assertTrue($this->eventBus->ofType(TaskAssigned::class)[0]->previousUserId?->equals($previous->getId()));
    }

    public function test_assigning_to_the_current_assignee_changes_nothing(): void
    {
        $user  = Mother::user();
        $task  = Mother::task(assignee: $user);
        $tasks = new InMemoryTaskRepository($task);

        $this->handler($tasks, new InMemoryUserRepository($user))(new AssignTaskToUserCommand($task->getId()->value(), $user->getId()->value()));

        $this->assertFalse($task->isUpdated());
        $this->assertSame(0, $tasks->updates);
        $this->assertTrue($this->appLogger->has('info', 'Task already assigned to this user'));
        $this->assertSame([], $this->eventBus->events);
    }

    public function test_fails_when_the_task_does_not_exist(): void
    {
        $taskId  = TaskId::new()->value();
        $handler = $this->handler(new InMemoryTaskRepository(), new InMemoryUserRepository(Mother::user()));

        $this->assertDomainError(TaskNotFoundException::ERROR_CODE, static fn () => $handler(new AssignTaskToUserCommand($taskId, UserId::new()->value())), ['task_id' => $taskId]);
        $this->assertSame([], $this->appLogger->records);
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
        $this->assertSame([], $this->eventBus->events);
    }

    private function handler(InMemoryTaskRepository $tasks, InMemoryUserRepository $users): AssignTaskToUserCommandHandler
    {
        return new AssignTaskToUserCommandHandler($tasks, $users, $this->eventBus, new MockClock(self::NOW), $this->appLogger);
    }
}
