<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\UpdateTask;

use App\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Application\Command\UpdateTask\UpdateTaskCommandHandler;
use App\Application\Command\UpdateTask\UpdateTaskResponse;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UpdateTaskCommandHandlerTest extends TestCase
{
    public function test_updates_selected_fields_persists_and_logs(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $taskIdStr = RamseyUuid::uuid4()->toString();

        $task = new Task(
            TaskId::fromString($taskIdStr),
            TaskTitle::fromString('Old Title'),
            TaskDescription::fromString('Old Desc'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $repo->expects($this->once())
            ->method('findById')
            ->with($this->callback(static fn (TaskId $id) => $id->value() === $taskIdStr))
            ->willReturn($task)
        ;

        $repo->expects($this->once())
            ->method('update')
            ->with($this->callback(static fn (Task $updated) => $updated->getTitle()->equals(TaskTitle::fromString('New Valid Title'))
                    && $updated->getDescription()->equals(TaskDescription::fromString('New Desc'))
                    && $updated->getStatus()->equals(TaskStatus::IN_PROGRESS)
                    && $updated->getPriority()->equals(TaskPriority::HIGH)
                    && true === $updated->getDueDate()?->equals(TaskDueDate::fromDate(new \DateTimeImmutable('2030-01-02T00:00:00+00:00')))
                    && true === $updated->isUpdated()))
        ;

        $logger->expects($this->once())
            ->method('info')
            ->with('Task Updated', $this->callback(static fn (array $ctx) => ($ctx['task_id'] ?? null) === $taskIdStr))
        ;

        $uc = new UpdateTaskCommandHandler($repo, $logger);

        $request = new UpdateTaskCommand(
            $taskIdStr,
            'New Valid Title',
            'New Desc',
            'in_progress',
            'high',
            '2030-01-02T00:00:00+00:00',
        );

        $response = $uc($request);

        $this->assertInstanceOf(UpdateTaskResponse::class, $response);
        $payload = $response->toArray();
        $this->assertSame('New Valid Title', $payload['title']);
        $this->assertSame('in_progress', $payload['status']);
        $this->assertSame('high', $payload['priority']);
        $this->assertSame('2030-01-02T00:00:00+00:00', $payload['dueDate']);
    }

    public function test_no_changes_detected_logs_and_does_not_persist(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $taskIdStr = RamseyUuid::uuid4()->toString();

        $task = new Task(
            TaskId::fromString($taskIdStr),
            TaskTitle::fromString('Keep Title'),
            TaskDescription::fromString('Keep Desc'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $repo->expects($this->once())->method('findById')->willReturn($task);
        $repo->expects($this->never())->method('update');

        $logger->expects($this->once())
            ->method('info')
            ->with('Task not updated (no changes detected)', $this->callback(static fn (array $ctx) => ($ctx['task_id'] ?? null) === $taskIdStr))
        ;

        $uc = new UpdateTaskCommandHandler($repo, $logger);

        $request = new UpdateTaskCommand(
            $taskIdStr,
            'Keep Title',
            'Keep Desc',
            'pending',
            'low',
            null,
        );

        $response = $uc($request);

        $this->assertInstanceOf(UpdateTaskResponse::class, $response);
        $this->assertSame('Keep Title', $response->toArray()['title']);
    }

    public function test_throws_when_task_not_found(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $repo->expects($this->once())->method('findById')->willReturn(null);
        $repo->expects($this->never())->method('update');
        $logger->expects($this->never())->method('info');

        $uc = new UpdateTaskCommandHandler($repo, $logger);

        $this->expectException(TaskNotFoundException::class);

        $uc(new UpdateTaskCommand(
            RamseyUuid::uuid4()->toString(),
            null,
            null,
            null,
            null,
            null,
        ));
    }
}
