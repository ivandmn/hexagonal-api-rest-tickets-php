<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\DateTime;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DateTimeTest extends TestCase
{
    use AssertsDomainError;

    public function test_normalizes_to_utc_keeping_the_instant(): void
    {
        $madrid = new \DateTimeImmutable('2030-05-06 18:00:00', new \DateTimeZone('Europe/Madrid'));
        $date   = new DateTime($madrid);

        $this->assertSame('UTC', $date->value()->getTimezone()->getName());
        $this->assertSame($madrid->getTimestamp(), $date->timestamp());
        $this->assertSame('2030-05-06T16:00:00+00:00', (string) $date);
    }

    public function test_drops_microseconds_like_the_database_does(): void
    {
        $date = new DateTime(new \DateTimeImmutable('2030-05-06 18:00:00.987654 UTC'));

        $this->assertSame('000000', $date->format('u'));
        $this->assertSame('2030-05-06T18:00:00+00:00', (string) $date);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validStrings(): iterable
    {
        yield 'utc offset' => ['2030-05-06T18:00:00+00:00', '2030-05-06T18:00:00+00:00'];
        yield 'zulu' => ['2030-05-06T18:00:00Z', '2030-05-06T18:00:00+00:00'];
        yield 'positive offset' => ['2030-05-06T18:00:00+02:00', '2030-05-06T16:00:00+00:00'];
        yield 'negative offset' => ['2030-05-06T18:00:00-05:00', '2030-05-06T23:00:00+00:00'];
        yield 'offset crossing the day' => ['2030-05-06T01:00:00+02:00', '2030-05-05T23:00:00+00:00'];
        yield 'milliseconds' => ['2030-05-06T18:00:00.123+02:00', '2030-05-06T16:00:00+00:00'];
        yield 'milliseconds zulu' => ['2030-05-06T18:00:00.999Z', '2030-05-06T18:00:00+00:00'];
        yield 'leap day' => ['2028-02-29T10:00:00+00:00', '2028-02-29T10:00:00+00:00'];
    }

    #[DataProvider('validStrings')]
    public function test_from_string_parses_rfc3339(string $value, string $expected): void
    {
        $this->assertSame($expected, (string) DateTime::fromString($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'missing timezone' => ['2030-01-01T10:00:00'];
        yield 'space separator' => ['2030-01-01 10:00:00+00:00'];
        yield 'date only' => ['2030-01-01'];
        yield 'relative' => ['tomorrow'];
        yield 'timestamp' => ['1893456000'];
        yield 'february 30th rolls over' => ['2030-02-30T10:00:00+00:00'];
        yield 'february 29th in a common year' => ['2030-02-29T10:00:00+00:00'];
        yield 'february 30th with milliseconds' => ['2030-02-30T10:00:00.000+00:00'];
        yield 'hour 25' => ['2030-01-01T25:00:00+00:00'];
        yield 'month 13' => ['2030-13-01T10:00:00+00:00'];
        yield 'microseconds' => ['2030-01-01T10:00:00.123456+00:00'];
        yield 'trailing data' => ['2030-01-01T10:00:00+00:00 extra'];
    }

    #[DataProvider('malformedStrings')]
    public function test_from_string_rejects_malformed_dates(string $value): void
    {
        $this->assertDomainError(
            InvalidValueException::INVALID_FORMAT,
            static fn () => DateTime::fromString($value),
            ['expected' => 'RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00)'],
        );
    }

    public function test_string_round_trip(): void
    {
        $date = DateTime::fromString('2030-05-06T18:00:00+02:00');

        $this->assertTrue($date->equals(DateTime::fromString((string) $date)));
    }

    public function test_from_timestamp(): void
    {
        $this->assertSame('2030-01-01T00:00:00+00:00', (string) DateTime::fromTimestamp(1893456000));
        $this->assertSame(1893456000, DateTime::fromTimestamp(1893456000)->timestamp());
    }

    public function test_now_comes_from_the_clock(): void
    {
        $clock = new MockClock('2030-05-06 18:00:00.5', 'Europe/Madrid');

        $this->assertSame('2030-05-06T16:00:00+00:00', (string) DateTime::now($clock));
    }

    public function test_equality_compares_the_instant_not_the_offset(): void
    {
        $a = DateTime::fromString('2030-05-06T18:00:00+02:00');
        $b = DateTime::fromString('2030-05-06T16:00:00Z');
        $c = DateTime::fromString('2030-05-06T16:00:01Z');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
        $this->assertNotSame($a, $b);
    }

    public function test_comparisons(): void
    {
        $early = DateTime::fromString('2030-05-06T10:00:00Z');
        $mid   = DateTime::fromString('2030-05-06T11:00:00Z');
        $late  = DateTime::fromString('2030-05-06T12:00:00Z');

        $this->assertTrue($early->isBefore($late));
        $this->assertFalse($late->isBefore($early));
        $this->assertFalse($early->isBefore($early));

        $this->assertTrue($late->isAfter($early));
        $this->assertFalse($early->isAfter($early));

        $this->assertTrue($early->isBeforeOrEqual($early));
        $this->assertTrue($early->isAfterOrEqual($early));

        $this->assertTrue($mid->isBetween($early, $late));
        $this->assertTrue($early->isBetween($early, $late));
        $this->assertTrue($late->isBetween($early, $late));
        $this->assertFalse($early->isBetween($mid, $late));

        $this->assertSame(-1, $early->compareTo($late));
        $this->assertSame(0, $early->compareTo(DateTime::fromString('2030-05-06T12:00:00+02:00')));
        $this->assertSame(1, $late->compareTo($early));
    }

    public function test_compare_to_sorts(): void
    {
        $dates = [
            DateTime::fromString('2030-05-06T12:00:00Z'),
            DateTime::fromString('2030-05-06T10:00:00Z'),
            DateTime::fromString('2030-05-06T11:00:00Z'),
        ];

        \usort($dates, static fn (DateTime $a, DateTime $b): int => $a->compareTo($b));

        $this->assertSame(
            ['2030-05-06T10:00:00+00:00', '2030-05-06T11:00:00+00:00', '2030-05-06T12:00:00+00:00'],
            \array_map(static fn (DateTime $d): string => (string) $d, $dates),
        );
    }

    public function test_is_same_day_uses_utc(): void
    {
        $a = DateTime::fromString('2030-05-06T00:30:00+02:00'); // 2030-05-05 22:30 UTC
        $b = DateTime::fromString('2030-05-05T08:00:00Z');
        $c = DateTime::fromString('2030-05-06T08:00:00Z');

        $this->assertTrue($a->isSameDay($b));
        $this->assertFalse($a->isSameDay($c));
    }

    public function test_arithmetic_returns_new_instances(): void
    {
        $date = DateTime::fromString('2030-05-06T18:00:00Z');

        $this->assertSame('2030-05-06T18:00:30+00:00', (string) $date->addSeconds(30));
        $this->assertSame('2030-05-06T17:59:30+00:00', (string) $date->addSeconds(-30));
        $this->assertSame('2030-05-06T18:15:00+00:00', (string) $date->addMinutes(15));
        $this->assertSame('2030-05-06T20:00:00+00:00', (string) $date->addHours(2));
        $this->assertSame('2030-05-08T18:00:00+00:00', (string) $date->addDays(2));
        $this->assertSame('2030-05-05T18:00:00+00:00', (string) $date->addDays(-1));
        $this->assertSame('2030-06-06T18:00:00+00:00', (string) $date->add(new \DateInterval('P1M')));
        $this->assertSame('2030-05-06T17:00:00+00:00', (string) $date->sub(new \DateInterval('PT1H')));

        $this->assertSame('2030-05-06T18:00:00+00:00', (string) $date, 'The original must not change');
    }

    public function test_day_boundaries(): void
    {
        $date = DateTime::fromString('2030-05-06T18:20:30Z');

        $this->assertSame('2030-05-06T00:00:00+00:00', (string) $date->startOfDay());
        $this->assertSame('2030-05-06T23:59:59+00:00', (string) $date->endOfDay());
    }

    public function test_differences(): void
    {
        $a = DateTime::fromString('2030-05-06T18:00:00Z');
        $b = DateTime::fromString('2030-05-08T19:30:00Z');

        $this->assertSame(178200, $a->secondsUntil($b));
        $this->assertSame(-178200, $b->secondsUntil($a));
        $this->assertSame(2, $a->diff($b)->days);
        $this->assertSame(1, $a->diff($b)->h);
    }

    public function test_in_timezone_is_presentation_only(): void
    {
        $date  = DateTime::fromString('2030-05-06T16:00:00Z');
        $local = $date->inTimezone(new \DateTimeZone('Europe/Madrid'));

        $this->assertSame('2030-05-06T18:00:00+02:00', $local->format(\DATE_RFC3339));
        $this->assertSame('2030-05-06T16:00:00+00:00', (string) $date);
    }

    public function test_json_serializes_as_rfc3339(): void
    {
        $date = DateTime::fromString('2030-05-06T18:00:00+02:00');

        $this->assertSame('{"at":"2030-05-06T16:00:00+00:00"}', \json_encode(['at' => $date]));
    }
}
