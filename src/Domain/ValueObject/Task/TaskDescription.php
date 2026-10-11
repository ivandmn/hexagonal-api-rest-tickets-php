<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;

final readonly class TaskDescription
{
    public const MAX_LENGTH = 1000;

    private string $value;

    /**
     * Surrounding whitespace is dropped; length is counted in characters, not bytes.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ('' === $value) {
            throw InvalidValueException::required();
        }

        if (\mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValueException::tooLong(self::MAX_LENGTH);
        }

        $this->value = $value;
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
