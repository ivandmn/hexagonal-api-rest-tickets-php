<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

/**
 * The date behaviour itself is covered by DateTimeTest; this covers what TaskDueDate adds.
 */
final class TaskDueDateTest extends TestCase
{
    use AssertsDomainError;

    public function test_is_a_date_time_value(): void
    {
        $due = TaskDueDate::fromString('2030-05-06T18:00:00+02:00');

        $this->assertInstanceOf(DateTime::class, $due);
        $this->assertSame('2030-05-06T16:00:00+00:00', (string) $due);
    }

    public function test_factories_and_operations_keep_the_subclass(): void
    {
        $due = TaskDueDate::fromDateTime(new \DateTimeImmutable('2030-05-06 18:00:00 UTC'));

        $this->assertInstanceOf(TaskDueDate::class, $due);
        $this->assertInstanceOf(TaskDueDate::class, $due->addDays(1));
    }

    public function test_a_past_due_date_can_still_be_built(): void
    {
        // Stored due dates must load back once they have passed: the rule lives in ensureNotBefore().
        $this->assertSame('2020-01-01T10:00:00+00:00', (string) TaskDueDate::fromString('2020-01-01T10:00:00+00:00'));
    }

    public function test_ensure_not_before_rejects_a_past_date(): void
    {
        $now = new \DateTimeImmutable('2030-05-06 18:00:00 UTC');
        $due = TaskDueDate::fromString('2030-05-06T17:59:59Z');

        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, static fn () => $due->ensureNotBefore($now));
    }

    public function test_ensure_not_before_accepts_now_and_later(): void
    {
        $now = new \DateTimeImmutable('2030-05-06 20:00:00', new \DateTimeZone('Europe/Madrid'));

        TaskDueDate::fromString('2030-05-06T18:00:00Z')->ensureNotBefore($now);
        TaskDueDate::fromString('2030-05-06T18:00:01Z')->ensureNotBefore($now);

        $this->addToAssertionCount(2);
    }

    public function test_from_string_rejects_overflowing_dates(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => TaskDueDate::fromString('2030-02-30T10:00:00+00:00'));
    }
}
