<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\User;

use App\Domain\Exception\InvalidValueException;

final readonly class UserName
{
    public const MIN_LENGTH = 5;
    public const MAX_LENGTH = 255;

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

        if (\mb_strlen($value) < self::MIN_LENGTH) {
            throw InvalidValueException::tooShort(self::MIN_LENGTH);
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
