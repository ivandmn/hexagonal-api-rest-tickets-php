<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\ListTasks;

use App\Application\Query\ListTasks\ListTasksQuery;
use App\Application\Query\ListTasks\ListTasksQueryHandler;
use App\Application\Query\ListTasks\ListTasksResponse;
use App\Domain\Exception\Task\InvalidTaskPriorityException;
use App\Domain\Exception\Task\InvalidTaskStatusException;
use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class ListTasksQueryHandlerTest extends TestCase
{
    public function test_calls_find_all_when_no_filters(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);

        $tasks = [
            new Task(TaskId::fromString(RamseyUuid::uuid4()->toString()), TaskTitle::fromString('Title A'), TaskDescription::fromString('D')),
            new Task(TaskId::fromString(RamseyUuid::uuid4()->toString()), TaskTitle::fromString('Title B'), TaskDescription::fromString('E')),
        ];

        $repo->expects($this->once())
            ->method('findAll')
            ->willReturn($tasks);

        $repo->expects($this->never())->method('findByFilters');

        $uc       = new ListTasksQueryHandler($repo);
        $response = $uc(new ListTasksQuery(null, null));

        $this->assertInstanceOf(ListTasksResponse::class, $response);
        $payload = $response->toArray();
        $this->assertSame(2, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['page']);
        $this->assertSame(50, $payload['meta']['limit']);
    }

    public function test_calls_find_by_filters_with_status_and_pagination(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);

        $expected = [
            new Task(TaskId::fromString(RamseyUuid::uuid4()->toString()), TaskTitle::fromString('Pending 1'), TaskDescription::fromString('D1'), TaskStatus::PENDING, TaskPriority::LOW),
        ];

        $repo->expects($this->once())
            ->method('findByFilters')
            ->with($this->equalTo(['status' => 'pending']), 2, 10)
            ->willReturn($expected);

        $uc       = new ListTasksQueryHandler($repo);
        $response = $uc(new ListTasksQuery('pending', null, 2, 10));

        $this->assertInstanceOf(ListTasksResponse::class, $response);
        $this->assertSame(1, $response->count());
    }

    public function test_calls_find_by_filters_with_priority_only(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);

        $expected = [
            new Task(
                TaskId::fromString(Uuid::uuid4()->toString()),
                TaskTitle::fromString('High One'),
                TaskDescription::fromString('D1'),
                TaskStatus::IN_PROGRESS,
                TaskPriority::HIGH
            ),
        ];

        $repo->expects($this->once())
            ->method('findByFilters')
            ->with($this->equalTo(['priority' => 'high']), 1, 50)
            ->willReturn($expected);

        $uc = new ListTasksQueryHandler($repo);

        $response = $uc(new ListTasksQuery(null, 'high', 1, 50));

        $this->assertInstanceOf(ListTasksResponse::class, $response);
        $this->assertSame(1, $response->count());
    }

    public function test_calls_find_by_filters_with_both_filters(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);

        $expected = [
            new Task(TaskId::fromString(RamseyUuid::uuid4()->toString()), TaskTitle::fromString('Title X'), TaskDescription::fromString('Y Desc'), TaskStatus::IN_PROGRESS, TaskPriority::MEDIUM),
        ];

        $repo->expects($this->once())
            ->method('findByFilters')
            ->with($this->equalTo(['status' => 'in_progress', 'priority' => 'medium']), 3, 5)
            ->willReturn($expected);

        $uc       = new ListTasksQueryHandler($repo);
        $response = $uc(new ListTasksQuery('in_progress', 'medium', 3, 5));

        $this->assertInstanceOf(ListTasksResponse::class, $response);
        $this->assertSame(1, $response->count());
    }

    public function test_throws_on_invalid_status(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $uc   = new ListTasksQueryHandler($repo);

        $this->expectException(InvalidTaskStatusException::class);
        $uc(new ListTasksQuery('not_a_status', null));
    }

    public function test_throws_on_invalid_priority(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $uc   = new ListTasksQueryHandler($repo);

        $this->expectException(InvalidTaskPriorityException::class);
        $uc(new ListTasksQuery(null, 'not_a_priority'));
    }
}
