<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\AssignTaskToUser;

use App\Application\Command\AssignTaskToUser\AssignTaskToUserCommand;
use App\Application\Command\AssignTaskToUser\AssignTaskToUserCommandHandler;
use App\Application\Command\AssignTaskToUser\AssignTaskToUserResponse;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\Task\UserNotFoundException;
use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class AssignTaskToUserCommandHandlerTest extends TestCase
{
    public function test_happy_path_assigns_and_updates_and_logs(): void
    {
        $taskRepo = $this->createMock(TaskRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $logger   = $this->createMock(LoggerInterface::class);

        $taskIdStr = RamseyUuid::uuid4()->toString();
        $userIdStr = RamseyUuid::uuid4()->toString();

        $task = new Task(
            TaskId::fromString($taskIdStr),
            TaskTitle::fromString('Test Title'),
            TaskDescription::fromString('Test Description'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $user = new User(
            UserId::fromString($userIdStr),
            UserEmail::fromString('assignee@example.com'),
            UserName::fromString('Assignee User'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $taskRepo->expects($this->once())
            ->method('findById')
            ->with($this->callback(static fn (TaskId $id) => $id->value() === $taskIdStr))
            ->willReturn($task)
        ;

        $userRepo->expects($this->once())
            ->method('findById')
            ->with($this->callback(static fn (UserId $id) => $id->value() === $userIdStr))
            ->willReturn($user)
        ;

        $taskRepo->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (Task $updated) => true === $updated->getAssignedUser()?->equals($user) && true === $updated->isUpdated()))
        ;

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Task Assigned to User',
                $this->callback(static fn (array $ctx) => ($ctx['task_id'] ?? null) === $taskIdStr
                        && ($ctx['task_title'] ?? null) === 'Test Title'
                        && ($ctx['user_id'] ?? null) === $userIdStr
                        && ($ctx['user_email'] ?? null) === 'assignee@example.com'),
            )
        ;

        $uc = new AssignTaskToUserCommandHandler($taskRepo, $userRepo, $logger);

        $response = $uc(new AssignTaskToUserCommand($taskIdStr, $userIdStr));

        $this->assertInstanceOf(AssignTaskToUserResponse::class, $response);
        $this->assertSame($user, $task->getAssignedUser());
    }

    public function test_no_change_when_already_assigned_logs_and_does_not_update(): void
    {
        $taskRepo = $this->createMock(TaskRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $logger   = $this->createMock(LoggerInterface::class);

        $taskIdStr = RamseyUuid::uuid4()->toString();
        $userIdStr = RamseyUuid::uuid4()->toString();

        $user = new User(
            UserId::fromString($userIdStr),
            UserEmail::fromString('assignee@example.com'),
            UserName::fromString('Assignee User'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $task = new Task(
            TaskId::fromString($taskIdStr),
            TaskTitle::fromString('Test Title'),
            TaskDescription::fromString('Test Description'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            $user,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $taskRepo->expects($this->once())
            ->method('findById')
            ->willReturn($task)
        ;

        $userRepo->expects($this->once())
            ->method('findById')
            ->willReturn($user)
        ;

        $taskRepo->expects($this->never())->method('update');

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Task not Assigned to User (no changes detected)',
                $this->callback(static fn (array $ctx) => ($ctx['task_id'] ?? null) === $taskIdStr
                        && ($ctx['task_title'] ?? null) === 'Test Title'
                        && ($ctx['user_id'] ?? null) === $userIdStr
                        && ($ctx['user_email'] ?? null) === 'assignee@example.com'),
            )
        ;

        $uc       = new AssignTaskToUserCommandHandler($taskRepo, $userRepo, $logger);
        $response = $uc(new AssignTaskToUserCommand($taskIdStr, $userIdStr));

        $this->assertInstanceOf(AssignTaskToUserResponse::class, $response);
        $this->assertSame($user, $task->getAssignedUser());
        $this->assertFalse($task->isUpdated());
    }

    public function test_throws_when_task_not_found(): void
    {
        $taskRepo = $this->createMock(TaskRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $logger   = $this->createMock(LoggerInterface::class);

        $taskRepo->expects($this->once())->method('findById')->willReturn(null);
        $userRepo->expects($this->never())->method('findById');
        $logger->expects($this->never())->method('info');

        $uc = new AssignTaskToUserCommandHandler($taskRepo, $userRepo, $logger);

        $this->expectException(TaskNotFoundException::class);
        $uc(new AssignTaskToUserCommand(RamseyUuid::uuid4()->toString(), RamseyUuid::uuid4()->toString()));
    }

    public function test_throws_when_user_not_found(): void
    {
        $taskRepo = $this->createMock(TaskRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $logger   = $this->createMock(LoggerInterface::class);

        $taskIdStr = RamseyUuid::uuid4()->toString();
        $task      = new Task(
            TaskId::fromString($taskIdStr),
            TaskTitle::fromString('Test Title'),
            TaskDescription::fromString('Test Description'),
        );

        $taskRepo->expects($this->once())->method('findById')->willReturn($task);
        $userRepo->expects($this->once())->method('findById')->willReturn(null);
        $taskRepo->expects($this->never())->method('update');
        $logger->expects($this->never())->method('info');

        $uc = new AssignTaskToUserCommandHandler($taskRepo, $userRepo, $logger);

        $this->expectException(UserNotFoundException::class);
        $uc(new AssignTaskToUserCommand($taskIdStr, RamseyUuid::uuid4()->toString()));
    }
}
