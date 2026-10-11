<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidUuidException;
use Ramsey\Uuid\Uuid as RamseyUuid;

readonly class Uuid
{
    public const LENGTH = 36;

    public function __construct(protected string $value)
    {
        if (!RamseyUuid::isValid($value)) {
            throw new InvalidUuidException('Invalid Uuid format');
        }
    }

    public static function fromString(string $uuid): static
    {
        return new static($uuid);
    }

    public static function new(): static
    {
        return new static(RamseyUuid::uuid4()->toString());
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
