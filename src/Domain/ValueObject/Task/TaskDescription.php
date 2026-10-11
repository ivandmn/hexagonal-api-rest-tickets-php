<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Task;

use App\Domain\Exception\Task\InvalidTaskDescription;

final readonly class TaskDescription
{
    public const MAX_LENGTH = 1000;

    public function __construct(private string $value)
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw new InvalidTaskDescription('Task description exceeds maximum characters length');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
