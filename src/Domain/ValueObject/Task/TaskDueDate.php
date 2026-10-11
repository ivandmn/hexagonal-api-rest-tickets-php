<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\TaskDueDateInPastException;

final class TaskDueDate
{
    public const FORMAT      = \DATE_RFC3339;
    public const FORMAT_NAME = 'RFC3339';

    public function __construct(private \DateTimeImmutable $date)
    {
        if ($date < new \DateTimeImmutable('now')) {
            throw new TaskDueDateInPastException($date);
        }
    }

    public static function fromDate(\DateTimeImmutable $date): self
    {
        return new self($date);
    }

    /**
     * Parses and applies every rule, including "not in the past".
     */
    public static function fromString(string $value): self
    {
        return new self(self::parse($value));
    }

    /**
     * Format rule only (RFC 3339). Overflowing dates such as February 30th are rejected
     * instead of silently rolling over to March.
     *
     * @throws InvalidValueException
     */
    public static function parse(string $value): \DateTimeImmutable
    {
        $date   = \DateTimeImmutable::createFromFormat(self::FORMAT, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (false === $date || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw InvalidValueException::invalidFormat(self::FORMAT_NAME . ' date-time (e.g. 2026-12-31T18:00:00+00:00)');
        }

        return $date;
    }

    public function value(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function equals(self $other): bool
    {
        return $this->date->getTimestamp() === $other->date->getTimestamp();
    }

    public function __toString(): string
    {
        return $this->date->format(self::FORMAT);
    }
}
