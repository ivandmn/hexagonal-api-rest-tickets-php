<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use Psr\Clock\ClockInterface;

/**
 * An instant in time, base for every date Value Object (like Uuid or Email for theirs).
 *
 * Always normalized to UTC and to whole seconds, which is exactly what the database keeps
 * (TIMESTAMP(0) WITH TIME ZONE): what is in memory is what gets stored and loaded back, so
 * equals() keeps holding after a round trip. The offset a client sent is not part of the value:
 * "18:00+02:00" and "16:00Z" are the same instant and both come out as "16:00:00+00:00".
 *
 * Immutable: every operation returns a new instance of the same class.
 */
readonly class DateTime implements \JsonSerializable
{
    public const FORMAT      = \DATE_RFC3339;
    public const FORMAT_NAME = 'RFC3339';
    public const TIMEZONE    = 'UTC';

    /** Accepted input formats: with or without milliseconds. */
    private const INPUT_FORMATS = [\DATE_RFC3339, \DATE_RFC3339_EXTENDED];

    private \DateTimeImmutable $value;

    final public function __construct(\DateTimeInterface $value)
    {
        $utc = \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone(self::TIMEZONE));

        $this->value = $utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), (int) $utc->format('s'));
    }

    /**
     * RFC 3339, with an explicit offset ("Z" or "±hh:mm") and optional milliseconds. Overflowing
     * dates such as February 30th are rejected instead of silently rolling over to March.
     *
     * @throws InvalidValueException
     */
    public static function fromString(string $value): static
    {
        foreach (self::INPUT_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            // Checked per attempt: an overflow only raises a warning, the date itself is not false.
            $errors = \DateTimeImmutable::getLastErrors();
            if (false !== $date && (false === $errors || 0 === $errors['warning_count'] + $errors['error_count'])) {
                return new static($date);
            }
        }

        throw InvalidValueException::invalidFormat(self::FORMAT_NAME . ' date-time (e.g. 2026-12-31T18:00:00+00:00)');
    }

    public static function fromDateTime(\DateTimeInterface $date): static
    {
        return new static($date);
    }

    public static function fromTimestamp(int $timestamp): static
    {
        return new static(new \DateTimeImmutable('@' . $timestamp));
    }

    public static function now(ClockInterface $clock): static
    {
        return new static($clock->now());
    }

    /**
     * In UTC, without microseconds.
     */
    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function timestamp(): int
    {
        return $this->value->getTimestamp();
    }

    public function format(string $format): string
    {
        return $this->value->format($format);
    }

    /**
     * For presentation only: the instant stays the same.
     */
    public function inTimezone(\DateTimeZone $timezone): \DateTimeImmutable
    {
        return $this->value->setTimezone($timezone);
    }

    public function equals(self $other): bool
    {
        return $this->timestamp() === $other->timestamp();
    }

    /**
     * -1, 0 or 1, usable with usort().
     */
    public function compareTo(self $other): int
    {
        return $this->timestamp() <=> $other->timestamp();
    }

    public function isBefore(self $other): bool
    {
        return $this->timestamp() < $other->timestamp();
    }

    public function isAfter(self $other): bool
    {
        return $this->timestamp() > $other->timestamp();
    }

    public function isBeforeOrEqual(self $other): bool
    {
        return $this->timestamp() <= $other->timestamp();
    }

    public function isAfterOrEqual(self $other): bool
    {
        return $this->timestamp() >= $other->timestamp();
    }

    /**
     * Both ends included.
     */
    public function isBetween(self $start, self $end): bool
    {
        return $this->isAfterOrEqual($start) && $this->isBeforeOrEqual($end);
    }

    /**
     * Same calendar day in UTC.
     */
    public function isSameDay(self $other): bool
    {
        return $this->value->format('Y-m-d') === $other->value->format('Y-m-d');
    }

    public function add(\DateInterval $interval): static
    {
        return new static($this->value->add($interval));
    }

    public function sub(\DateInterval $interval): static
    {
        return new static($this->value->sub($interval));
    }

    /**
     * Negative amounts go back in time.
     */
    public function addSeconds(int $seconds): static
    {
        return new static($this->value->modify(\sprintf('%+d seconds', $seconds)));
    }

    public function addMinutes(int $minutes): static
    {
        return new static($this->value->modify(\sprintf('%+d minutes', $minutes)));
    }

    public function addHours(int $hours): static
    {
        return new static($this->value->modify(\sprintf('%+d hours', $hours)));
    }

    public function addDays(int $days): static
    {
        return new static($this->value->modify(\sprintf('%+d days', $days)));
    }

    /**
     * 00:00:00 UTC of the same day.
     */
    public function startOfDay(): static
    {
        return new static($this->value->setTime(0, 0));
    }

    /**
     * 23:59:59 UTC of the same day (the last instant the database can hold).
     */
    public function endOfDay(): static
    {
        return new static($this->value->setTime(23, 59, 59));
    }

    /**
     * Positive when $other is later.
     */
    public function secondsUntil(self $other): int
    {
        return $other->timestamp() - $this->timestamp();
    }

    public function diff(self $other): \DateInterval
    {
        return $this->value->diff($other->value);
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        return $this->value->format(self::FORMAT);
    }
}
