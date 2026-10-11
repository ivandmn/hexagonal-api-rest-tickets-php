<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\GetTaskDetails;

use App\Application\Query\GetTaskDetails\GetTaskDetailsQuery;
use App\Application\Query\GetTaskDetails\GetTaskDetailsQueryHandler;
use App\Application\Query\GetTaskDetails\GetTaskDetailsResponse;
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

final class GetTaskDetailsQueryHandlerTest extends TestCase
{
    public function test_returns_response_when_task_found(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);

        $taskIdStr = RamseyUuid::uuid4()->toString();
        $task      = new Task(
            TaskId::fromString($taskIdStr),
            TaskTitle::fromString('Details Title'),
            TaskDescription::fromString('Details Description'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00')
        );

        $repo->expects($this->once())
            ->method('findById')
            ->with($this->callback(static fn (TaskId $id) => $id->value() === $taskIdStr))
            ->willReturn($task);

        $uc = new GetTaskDetailsQueryHandler($repo);

        $response = $uc(new GetTaskDetailsQuery($taskIdStr));

        $this->assertInstanceOf(GetTaskDetailsResponse::class, $response);
        $payload = $response->toArray();
        $this->assertSame($taskIdStr, $payload['id']);
        $this->assertSame('Details Title', $payload['title']);
    }

    public function test_throws_when_task_not_found(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);

        $repo->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $uc = new GetTaskDetailsQueryHandler($repo);

        $this->expectException(TaskNotFoundException::class);
        $uc(new GetTaskDetailsQuery(RamseyUuid::uuid4()->toString()));
    }
}
