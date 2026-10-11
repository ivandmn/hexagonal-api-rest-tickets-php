<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Tests\Support\FutureDate;
use PHPUnit\Framework\TestCase;

final class TaskDueDateTest extends TestCase
{
    public function test_creates_valid_future_due_date(): void
    {
        $future = new \DateTimeImmutable('now')->modify('+1 day');
        $due    = new TaskDueDate($future);

        $this->assertInstanceOf(TaskDueDate::class, $due);
        $this->assertInstanceOf(\DateTimeImmutable::class, $due->value());
        $this->assertSame($future->getTimestamp(), $due->value()->getTimestamp());
    }

    public function test_from_date_creates_valid_due_date(): void
    {
        $future = new \DateTimeImmutable('now')->modify('+2 days');
        $due    = TaskDueDate::fromDate($future);

        $this->assertInstanceOf(TaskDueDate::class, $due);
        $this->assertSame($future->getTimestamp(), $due->value()->getTimestamp());
    }

    public function test_throws_exception_when_date_in_past(): void
    {
        $this->expectException(TaskDueDateInPastException::class);
        $this->expectExceptionMessage('Due date cannot be in the past');

        $past = new \DateTimeImmutable('now')->modify('-1 second');
        new TaskDueDate($past);
    }

    public function test_equals_returns_true_for_same_timestamp(): void
    {
        $base = FutureDate::on();
        $a    = new TaskDueDate(clone $base);
        $b    = new TaskDueDate(clone $base);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_timestamp(): void
    {
        $base = FutureDate::on();
        $a    = new TaskDueDate(clone $base);
        $b    = new TaskDueDate((clone $base)->modify('+1 second'));

        $this->assertFalse($a->equals($b));
    }

    public function test_string_representation_is_rfc3339(): void
    {
        $future = FutureDate::on(5, 6, '12:34:56')->setTimezone(new \DateTimeZone('+02:00'));
        $due    = new TaskDueDate(clone $future);

        $this->assertSame($future->format(TaskDueDate::FORMAT), (string) $due);

        $parsed = \DateTimeImmutable::createFromFormat(TaskDueDate::FORMAT, (string) $due);
        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed);
        $this->assertSame((string) $due, $parsed->format(TaskDueDate::FORMAT));
    }
}
