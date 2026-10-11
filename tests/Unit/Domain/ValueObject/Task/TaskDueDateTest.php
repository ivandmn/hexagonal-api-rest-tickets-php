<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\FutureDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskDueDateTest extends TestCase
{
    use AssertsDomainError;

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
        $past = new \DateTimeImmutable('now')->modify('-1 second');
        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, static fn () => new TaskDueDate($past));
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

    public function test_parse_accepts_rfc3339(): void
    {
        $this->assertSame('2030-05-06T12:34:56+02:00', TaskDueDate::parse('2030-05-06T12:34:56+02:00')->format(\DATE_ATOM));
    }

    #[DataProvider('malformedDates')]
    public function test_parse_rejects_malformed_dates(string $value): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => TaskDueDate::parse($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDates(): iterable
    {
        yield 'missing timezone' => ['2030-01-01T10:00:00'];
        yield 'space separator' => ['2030-01-01 10:00:00'];
        yield 'not a date' => ['tomorrow'];
        yield 'february 30th rolls over' => ['2030-02-30T10:00:00+00:00'];
        yield 'hour 25' => ['2030-01-01T25:00:00+00:00'];
    }

    public function test_from_string_applies_every_rule(): void
    {
        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, static fn () => TaskDueDate::fromString('2020-01-01T10:00:00+00:00'), ['dueDate' => '2020-01-01T10:00:00+00:00']);
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => TaskDueDate::fromString('nope'));
        $this->assertInstanceOf(TaskDueDate::class, TaskDueDate::fromString(FutureDate::on()->format(\DATE_ATOM)));
    }
}
