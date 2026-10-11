<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Generic value errors raised by Value Objects. They carry no field: the command or query that
 * builds the Value Object tags it with the input name (see App\Application\Validation\Field).
 */
final class InvalidValueException extends DomainException
{
    public const REQUIRED       = 'VALUE_REQUIRED';
    public const INVALID        = 'VALUE_INVALID';
    public const INVALID_FORMAT = 'VALUE_INVALID_FORMAT';
    public const INVALID_CHOICE = 'VALUE_INVALID_CHOICE';
    public const OUT_OF_RANGE   = 'VALUE_OUT_OF_RANGE';
    public const BELOW_MINIMUM  = 'VALUE_BELOW_MINIMUM';
    public const ABOVE_MAXIMUM  = 'VALUE_ABOVE_MAXIMUM';
    public const TOO_SHORT      = 'VALUE_TOO_SHORT';
    public const TOO_LONG       = 'VALUE_TOO_LONG';

    public static function required(?\Throwable $previous = null): self
    {
        return new self(self::REQUIRED, 'Value is required', [], 0, $previous);
    }

    public static function invalid(string $reason, ?\Throwable $previous = null): self
    {
        return new self(self::INVALID, \sprintf('Invalid value: %s', $reason), [], 0, $previous);
    }

    public static function invalidFormat(string $expected, ?\Throwable $previous = null): self
    {
        return new self(
            self::INVALID_FORMAT,
            \sprintf('Invalid format, expected %s', $expected),
            ['expected' => $expected],
            0,
            $previous,
        );
    }

    /**
     * @param list<string> $allowed
     */
    public static function invalidChoice(string $actual, array $allowed): self
    {
        return new self(
            self::INVALID_CHOICE,
            \sprintf('"%s" is not one of: %s', $actual, \implode(', ', $allowed)),
            ['allowed' => \implode(', ', $allowed)],
        );
    }

    public static function outOfRange(float|int $min, float|int $max, float|int $actual): self
    {
        return new self(
            self::OUT_OF_RANGE,
            \sprintf('Value %s out of range [%s, %s]', $actual, $min, $max),
            ['min' => $min, 'max' => $max],
        );
    }

    public static function belowMinimum(float|int $min, float|int $actual): self
    {
        return new self(self::BELOW_MINIMUM, \sprintf('Value %s is below %s', $actual, $min), ['min' => $min]);
    }

    public static function aboveMaximum(float|int $max, float|int $actual): self
    {
        return new self(self::ABOVE_MAXIMUM, \sprintf('Value %s is above %s', $actual, $max), ['max' => $max]);
    }

    public static function tooShort(int $min): self
    {
        return new self(self::TOO_SHORT, \sprintf('Value shorter than %d characters', $min), ['min' => $min]);
    }

    public static function tooLong(int $max): self
    {
        return new self(self::TOO_LONG, \sprintf('Value longer than %d characters', $max), ['max' => $max]);
    }
}
