<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Task;

use App\Domain\Exception\Task\TaskDueDateInPastException;

final class TaskDueDate
{
    public const FORMAT      = \DATE_RFC3339;
    public const FORMAT_NAME = 'RFC3339';

    public function __construct(private \DateTimeImmutable $date)
    {
        if ($date < new \DateTimeImmutable('now')) {
            throw new TaskDueDateInPastException('Due date cannot be in the past');
        }
    }

    public static function fromDate(\DateTimeImmutable $date): self
    {
        return new self($date);
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
