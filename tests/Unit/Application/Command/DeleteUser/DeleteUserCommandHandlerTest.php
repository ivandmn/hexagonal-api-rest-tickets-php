<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\DeleteUser;

use App\Application\Command\DeleteUser\DeleteUserCommand;
use App\Application\Command\DeleteUser\DeleteUserCommandHandler;
use App\Domain\Exception\User\UserDeletionNotAllowedException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Double\SpyAppLogger;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class DeleteUserCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private User $user;
    private InMemoryUserRepository $users;
    private SpyAppLogger $logger;

    protected function setUp(): void
    {
        $this->user   = Mother::user(email: 'jane@example.com');
        $this->users  = new InMemoryUserRepository($this->user);
        $this->logger = new SpyAppLogger();
    }

    public function test_deletes_a_user_whose_tasks_are_all_completed(): void
    {
        $tasks = new InMemoryTaskRepository(
            Mother::task(status: TaskStatus::COMPLETED, assignee: $this->user),
            Mother::task(status: TaskStatus::COMPLETED, assignee: $this->user),
            Mother::task(status: TaskStatus::IN_PROGRESS), // someone else's
        );

        $this->handler($tasks)(new DeleteUserCommand($this->user->getId()->value()));

        $this->assertNull($this->users->findById($this->user->getId()));
        $this->assertTrue($this->logger->has('info', 'User deleted', ['user_id' => $this->user->getId()->value(), 'deleted_tasks' => 2]));
    }

    public function test_deletes_a_user_without_tasks(): void
    {
        $this->handler(new InMemoryTaskRepository())(new DeleteUserCommand($this->user->getId()->value()));

        $this->assertSame([], $this->users->findAll());
    }

    public function test_refuses_while_some_task_is_not_completed(): void
    {
        $tasks = new InMemoryTaskRepository(
            Mother::task(status: TaskStatus::PENDING, assignee: $this->user),
            Mother::task(status: TaskStatus::IN_PROGRESS, assignee: $this->user),
            Mother::task(status: TaskStatus::COMPLETED, assignee: $this->user),
        );
        $userId = $this->user->getId()->value();

        $this->assertDomainError(
            UserDeletionNotAllowedException::ERROR_CODE,
            fn () => $this->handler($tasks)(new DeleteUserCommand($userId)),
            ['user_id' => $userId, 'unfinished_tasks' => 2],
        );
        $this->assertNotNull($this->users->findById($this->user->getId()));
    }

    public function test_fails_when_the_user_does_not_exist(): void
    {
        $userId = UserId::new()->value();

        $this->assertDomainError(
            UserNotFoundException::ERROR_CODE,
            fn () => $this->handler(new InMemoryTaskRepository())(new DeleteUserCommand($userId)),
            ['user_id' => $userId],
        );
    }

    private function handler(InMemoryTaskRepository $tasks): DeleteUserCommandHandler
    {
        return new DeleteUserCommandHandler($this->users, $tasks, $this->logger);
    }
}
