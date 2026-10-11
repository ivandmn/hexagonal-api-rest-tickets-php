<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\ListTasks;

use App\Application\Query\ListTasks\ListTasksQuery;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class ListTasksQueryTest extends TestCase
{
    use AssertsDomainError;

    public function test_getters_return_values(): void
    {
        $req = new ListTasksQuery('pending', 'low', 2, 10);

        $this->assertSame(TaskStatus::PENDING, $req->getStatus());
        $this->assertSame(TaskPriority::LOW, $req->getPriority());
        $this->assertSame(2, $req->getPage());
        $this->assertSame(10, $req->getLimit());
    }

    public function test_defaults_for_page_and_limit_when_omitted(): void
    {
        $req = new ListTasksQuery(null, null);

        $this->assertNull($req->getStatus());
        $this->assertNull($req->getPriority());
        $this->assertSame(1, $req->getPage());
        $this->assertSame(50, $req->getLimit());
    }

    public function test_normalises_out_of_range_pagination(): void
    {
        $this->assertSame(1, new ListTasksQuery(null, null, 0, 10)->getPage());
        $this->assertSame(1, new ListTasksQuery(null, null, -3, 10)->getPage());
        $this->assertSame(1, new ListTasksQuery(null, null, 1, 0)->getLimit());
        $this->assertSame(ListTasksQuery::MAX_LIMIT, new ListTasksQuery(null, null, 1, 5000)->getLimit());
    }

    public function test_rejects_unknown_filter_values_when_built(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_CHOICE, static fn () => new ListTasksQuery('done', null));
        $this->assertDomainError(InvalidValueException::INVALID_CHOICE, static fn () => new ListTasksQuery(null, 'urgent'));
    }
}
