<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\Task\ListTasks;

use App\Application\Query\Task\ListTasks\ListTasksQuery;
use App\Application\Query\Task\ListTasks\ListTasksQueryHandler;
use App\Domain\Repository\TaskCriteria;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class ListTasksQueryHandlerTest extends TestCase
{
    public function test_without_filters_returns_the_first_page_and_the_overall_total(): void
    {
        $repo = new InMemoryTaskRepository(...\array_map(
            static fn (int $i) => Mother::task(createdAt: new \DateTimeImmutable("2026-01-01 +{$i} minutes")),
            \range(1, 3),
        ));

        $view = new ListTasksQueryHandler($repo)(new ListTasksQuery(page: 1, limit: 2));

        $this->assertCount(2, $view->items);
        $this->assertSame(3, $view->total, 'the total covers every page');
        $this->assertSame(1, $view->page);
        $this->assertSame(2, $view->limit);
    }

    public function test_an_empty_result_is_an_empty_list(): void
    {
        $view = new ListTasksQueryHandler(new InMemoryTaskRepository())(new ListTasksQuery());

        $this->assertSame([], $view->items);
        $this->assertSame(0, $view->total);
        $this->assertSame(ListTasksQuery::DEFAULT_LIMIT, $view->limit);
    }

    public function test_filters_by_status_and_priority(): void
    {
        $match = Mother::task(status: TaskStatus::IN_PROGRESS, priority: TaskPriority::MEDIUM);
        $repo  = new InMemoryTaskRepository(
            $match,
            Mother::task(status: TaskStatus::IN_PROGRESS, priority: TaskPriority::HIGH),
            Mother::task(status: TaskStatus::PENDING, priority: TaskPriority::MEDIUM),
        );

        $view = new ListTasksQueryHandler($repo)(new ListTasksQuery('in_progress', 'medium'));

        $this->assertSame(1, $view->total);
        $this->assertSame($match->getId()->value(), $view->items[0]->id);
    }

    public function test_filters_by_assignee_and_due_date_range(): void
    {
        $jane  = Mother::user();
        $john  = Mother::user(email: 'john@example.com');
        $match = Mother::task(assignee: $jane, dueDate: TaskDueDate::fromString('2030-01-15T10:00:00Z'));
        $repo  = new InMemoryTaskRepository(
            $match,
            Mother::task(assignee: $jane, dueDate: TaskDueDate::fromString('2030-02-15T10:00:00Z')), // too late
            Mother::task(assignee: $jane), // no due date
            Mother::task(assignee: $john, dueDate: TaskDueDate::fromString('2030-01-15T10:00:00Z')), // someone else's
        );

        $view = new ListTasksQueryHandler($repo)(new ListTasksQuery(
            assignedTo: $jane->getId()->value(),
            dueFrom: '2030-01-01T00:00:00Z',
            dueTo: '2030-01-31T23:59:59Z',
        ));

        $this->assertSame(1, $view->total);
        $this->assertSame($match->getId()->value(), $view->items[0]->id);
    }

    public function test_passes_the_typed_criteria_and_pagination_to_the_repository(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->expects($this->once())
            ->method('findByCriteria')
            ->with(new TaskCriteria(TaskStatus::PENDING, null), 2, 10)
            ->willReturn([])
        ;
        $repo->expects($this->once())
            ->method('countByCriteria')
            ->with(new TaskCriteria(TaskStatus::PENDING, null))
            ->willReturn(0)
        ;

        new ListTasksQueryHandler($repo)(new ListTasksQuery('pending', page: 2, limit: 10));
    }

    public function test_passes_every_filter_to_the_repository(): void
    {
        $userId   = UserId::new();
        $criteria = new TaskCriteria(
            TaskStatus::COMPLETED,
            TaskPriority::HIGH,
            $userId,
            DateTime::fromString('2030-01-01T00:00:00Z'),
            DateTime::fromString('2030-01-31T23:59:59Z'),
        );

        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->expects($this->once())->method('findByCriteria')->with($criteria, 1, ListTasksQuery::DEFAULT_LIMIT)->willReturn([]);
        $repo->expects($this->once())->method('countByCriteria')->with($criteria)->willReturn(0);

        new ListTasksQueryHandler($repo)(new ListTasksQuery('completed', 'high', $userId->value(), '2030-01-01T00:00:00Z', '2030-01-31T23:59:59Z'));
    }
}
