<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\DeleteUser;

use App\Application\Command\DeleteUser\DeleteUserCommand;
use App\Application\Command\DeleteUser\DeleteUserCommandHandler;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\User\UserDeletionNotAllowedException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class DeleteUserCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private User $user;

    protected function setUp(): void
    {
        $this->user = new User(UserId::new(), UserEmail::fromString('jane@example.com'), UserName::fromString('Jane Doe'));
    }

    public function test_deletes_a_user_whose_tasks_are_all_completed(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->willReturn($this->user);
        $users->expects($this->once())->method('delete')->with($this->user);

        $tasks = $this->createStub(TaskRepositoryInterface::class);
        $tasks->method('countUnfinishedByUser')->willReturn(0);
        $tasks->method('countByUser')->willReturn(3);

        $response = $this->handler($users, $tasks)(new DeleteUserCommand($this->user->getId()->value()));

        $this->assertSame([
            'message'       => 'User "jane@example.com" has been successfully deleted',
            'user_id'       => $this->user->getId()->value(),
            'deleted_tasks' => 3,
        ], $response->toArray());
    }

    public function test_refuses_while_some_task_is_not_completed(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->willReturn($this->user);
        $users->expects($this->never())->method('delete');

        $tasks = $this->createStub(TaskRepositoryInterface::class);
        $tasks->method('countUnfinishedByUser')->willReturn(2);

        $this->assertDomainError(
            UserDeletionNotAllowedException::ERROR_CODE,
            fn () => $this->handler($users, $tasks)(new DeleteUserCommand($this->user->getId()->value())),
            ['userId' => $this->user->getId()->value(), 'unfinishedTasks' => 2],
        );
    }

    public function test_fails_when_the_user_does_not_exist(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->method('findById')->willReturn(null);
        $users->expects($this->never())->method('delete');

        $userId = UserId::new()->value();

        $this->assertDomainError(
            UserNotFoundException::ERROR_CODE,
            fn () => $this->handler($users, $this->createStub(TaskRepositoryInterface::class))(new DeleteUserCommand($userId)),
            ['userId' => $userId],
        );
    }

    private function handler(UserRepositoryInterface $users, TaskRepositoryInterface $tasks): DeleteUserCommandHandler
    {
        return new DeleteUserCommandHandler($users, $tasks, $this->createStub(LoggerInterface::class));
    }
}
