<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\DeleteTask;

use App\Application\Command\DeleteTask\DeleteTaskCommand;
use App\Application\Command\DeleteTask\DeleteTaskCommandHandler;
use App\Application\Command\DeleteTask\DeleteTaskResponse;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class DeleteTaskCommandHandlerTest extends TestCase
{
    public function test_deletes_task_and_logs(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $taskId = TaskId::fromString(RamseyUuid::uuid7()->toString());
        $task   = new Task(
            $taskId,
            TaskTitle::fromString('Deletable Task'),
            TaskDescription::fromString('ok'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00')
        );

        $repo->expects($this->once())
            ->method('findById')
            ->with($this->callback(static fn (TaskId $id) => $id->value() === $taskId->value()))
            ->willReturn($task);

        $repo->expects($this->once())
            ->method('delete')
            ->with($this->identicalTo($task));

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Task deleted',
                $this->callback(static fn (array $ctx) => ($ctx['task_id'] ?? null) === $taskId->value()
                        && isset($ctx['task_title'])
                        && $ctx['task_title'] === $task->getTitle())
            );

        $uc       = new DeleteTaskCommandHandler($repo, $logger);
        $response = $uc(new DeleteTaskCommand($taskId->value()));

        $this->assertInstanceOf(DeleteTaskResponse::class, $response);
        $payload = $response->toArray();
        $this->assertSame($taskId->value(), $payload['task_id']);
    }

    public function test_throws_when_task_not_found(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repo->expects($this->once())->method('findById')->willReturn(null);
        $repo->expects($this->never())->method('delete');
        $logger->expects($this->never())->method('info');

        $uc = new DeleteTaskCommandHandler($repo, $logger);

        $this->expectException(TaskNotFoundException::class);
        $uc(new DeleteTaskCommand(RamseyUuid::uuid7()->toString()));
    }

    public function test_throws_when_task_cannot_be_deleted(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $taskId = TaskId::fromString(RamseyUuid::uuid7()->toString());
        $task   = new Task(
            $taskId,
            TaskTitle::fromString('In Progress'),
            TaskDescription::fromString('no'),
            TaskStatus::IN_PROGRESS,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00')
        );

        $repo->expects($this->once())->method('findById')->willReturn($task);
        $repo->expects($this->never())->method('delete');
        $logger->expects($this->never())->method('info');

        $uc = new DeleteTaskCommandHandler($repo, $logger);

        $this->expectException(TaskDeletionNotAllowedException::class);
        $uc(new DeleteTaskCommand($taskId->value()));
    }
}
