<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

readonly class Email
{
    public const MAX_LENGTH = 255;

    public function __construct(protected string $value)
    {
        // Length first: filter_var() also rejects very long addresses, which would hide the real reason.
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidValueException::tooLong(self::MAX_LENGTH);
        }

        if (!\filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidValueException::invalidFormat('email address');
        }
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
