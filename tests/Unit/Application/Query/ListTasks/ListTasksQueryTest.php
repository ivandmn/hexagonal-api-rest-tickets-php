<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\ListTasks;

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

    public function test_rejects_out_of_range_pagination(): void
    {
        $this->assertFieldError('page', InvalidValueException::BELOW_MINIMUM, static fn () => new ListTasksQuery(null, null, 0, 10), ['min' => 1]);
        $this->assertFieldError('limit', InvalidValueException::OUT_OF_RANGE, static fn () => new ListTasksQuery(null, null, 1, 0), ['min' => 1, 'max' => ListTasksQuery::MAX_LIMIT]);
        $this->assertFieldError('limit', InvalidValueException::OUT_OF_RANGE, static fn () => new ListTasksQuery(null, null, 1, ListTasksQuery::MAX_LIMIT + 1), ['min' => 1, 'max' => ListTasksQuery::MAX_LIMIT]);
    }

    public function test_rejects_unknown_filter_values_when_built(): void
    {
        $this->assertFieldError('status', InvalidValueException::INVALID_CHOICE, static fn () => new ListTasksQuery('done', null));
        $this->assertFieldError('priority', InvalidValueException::INVALID_CHOICE, static fn () => new ListTasksQuery(null, 'urgent'));
    }
}
