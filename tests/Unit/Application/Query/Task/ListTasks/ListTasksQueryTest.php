<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\Task\ListTasks;

use App\Application\Query\Task\ListTasks\ListTasksQuery;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class ListTasksQueryTest extends TestCase
{
    use AssertsDomainError;

    public function test_getters_return_values(): void
    {
        $req = new ListTasksQuery('pending', 'low', page: 2, limit: 10);

        $this->assertSame(TaskStatus::PENDING, $req->getStatus());
        $this->assertSame(TaskPriority::LOW, $req->getPriority());
        $this->assertSame(2, $req->getPage());
        $this->assertSame(10, $req->getLimit());
    }

    public function test_defaults_for_page_and_limit_when_omitted(): void
    {
        $req = new ListTasksQuery();

        $this->assertNull($req->getStatus());
        $this->assertNull($req->getPriority());
        $this->assertNull($req->getAssignedTo());
        $this->assertNull($req->getDueFrom());
        $this->assertNull($req->getDueTo());
        $this->assertSame(1, $req->getPage());
        $this->assertSame(50, $req->getLimit());
    }

    public function test_rejects_out_of_range_pagination(): void
    {
        $this->assertFieldError('page', InvalidValueException::BELOW_MINIMUM, static fn () => new ListTasksQuery(page: 0, limit: 10), ['min' => 1]);
        $this->assertFieldError('limit', InvalidValueException::OUT_OF_RANGE, static fn () => new ListTasksQuery(page: 1, limit: 0), ['min' => 1, 'max' => ListTasksQuery::MAX_LIMIT]);
        $this->assertFieldError('limit', InvalidValueException::OUT_OF_RANGE, static fn () => new ListTasksQuery(page: 1, limit: ListTasksQuery::MAX_LIMIT + 1), ['min' => 1, 'max' => ListTasksQuery::MAX_LIMIT]);
    }

    public function test_rejects_unknown_filter_values_when_built(): void
    {
        $this->assertFieldError('status', InvalidValueException::INVALID_CHOICE, static fn () => new ListTasksQuery('done', null));
        $this->assertFieldError('priority', InvalidValueException::INVALID_CHOICE, static fn () => new ListTasksQuery(null, 'urgent'));
    }

    public function test_builds_the_assignee_and_due_date_filters(): void
    {
        $userId = UserId::new()->value();

        $req = new ListTasksQuery(assignedTo: $userId, dueFrom: '2020-01-01T02:00:00+02:00', dueTo: '2020-01-31T23:59:59Z');

        $this->assertSame($userId, $req->getAssignedTo()?->value());
        $this->assertSame('2020-01-01T00:00:00+00:00', (string) $req->getDueFrom(), 'past dates are fine in a filter');
        $this->assertSame('2020-01-31T23:59:59+00:00', (string) $req->getDueTo());
    }

    public function test_a_single_instant_range_is_allowed(): void
    {
        $req = new ListTasksQuery(dueFrom: '2030-01-01T10:00:00Z', dueTo: '2030-01-01T12:00:00+02:00');

        $this->assertTrue($req->getDueFrom()?->equals($req->getDueTo() ?? $this->fail()));
    }

    public function test_rejects_malformed_assignee_and_due_dates(): void
    {
        $this->assertFieldError('assignedTo', InvalidValueException::INVALID_FORMAT, static fn () => new ListTasksQuery(assignedTo: 'not-a-uuid'));
        $this->assertFieldError('dueFrom', InvalidValueException::INVALID_FORMAT, static fn () => new ListTasksQuery(dueFrom: '2030-01-01'));
        $this->assertFieldError('dueTo', InvalidValueException::INVALID_FORMAT, static fn () => new ListTasksQuery(dueTo: 'tomorrow'));
    }

    public function test_rejects_a_due_to_before_due_from(): void
    {
        $this->assertFieldError('dueTo', InvalidValueException::INVALID, static fn () => new ListTasksQuery(dueFrom: '2030-01-02T00:00:00Z', dueTo: '2030-01-01T23:59:59Z'));
    }
}
