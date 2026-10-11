<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;

enum TaskStatus: string
{
    case PENDING     = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED   = 'completed';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value)
            ?? throw InvalidValueException::invalidChoice($value, \array_column(self::cases(), 'value'));
    }

    public function isPending(): bool
    {
        return self::PENDING === $this;
    }

    public function isInProgress(): bool
    {
        return self::IN_PROGRESS === $this;
    }

    public function isCompleted(): bool
    {
        return self::COMPLETED === $this;
    }

    public function equals(TaskStatus $other): bool
    {
        return $this === $other;
    }

    public function canTransitionTo(TaskStatus $newStatus): bool
    {
        return match ([$this, $newStatus]) {
            [self::PENDING, self::IN_PROGRESS], [self::IN_PROGRESS, self::COMPLETED] => true,
            default                                                                  => false,
        };
    }
}
