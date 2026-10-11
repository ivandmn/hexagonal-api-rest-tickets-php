<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

/**
 * Subclasses (UserEmail) keep the constructor signature: fromString() uses `new static`.
 *
 * @phpstan-consistent-constructor
 */
readonly class Email
{
    public const MAX_LENGTH = 255;

    protected string $value;

    public function __construct(string $value)
    {
        $value = \trim($value);

        if ('' === $value) {
            throw InvalidValueException::required();
        }

        // Length first: filter_var() also rejects very long addresses, which would hide the real reason.
        if (\mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValueException::tooLong(self::MAX_LENGTH);
        }

        if (!\filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidValueException::invalidFormat('email address');
        }

        $this->value = $value;
    }

    public static function fromString(string $email): static
    {
        return new static($email);
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
