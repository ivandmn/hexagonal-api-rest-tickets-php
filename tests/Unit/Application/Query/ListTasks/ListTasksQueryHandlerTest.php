<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\ListTasks;

use App\Application\Query\ListTasks\ListTasksQuery;
use App\Application\Query\ListTasks\ListTasksQueryHandler;
use App\Domain\Repository\TaskCriteria;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
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

        $view = new ListTasksQueryHandler($repo)(new ListTasksQuery(null, null, 1, 2));

        $this->assertCount(2, $view->items);
        $this->assertSame(3, $view->total, 'the total covers every page');
        $this->assertSame(1, $view->page);
        $this->assertSame(2, $view->limit);
    }

    public function test_an_empty_result_is_an_empty_list(): void
    {
        $view = new ListTasksQueryHandler(new InMemoryTaskRepository())(new ListTasksQuery(null, null));

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

        new ListTasksQueryHandler($repo)(new ListTasksQuery('pending', null, 2, 10));
    }
}
