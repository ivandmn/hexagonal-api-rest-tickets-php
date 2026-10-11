<?php

declare(strict_types=1);

namespace App\UI\Http\Request;

use App\Domain\Exception\InvalidValueException;
use App\UI\Http\Exception\ApiErrorCode;

/**
 * The request input does not have the expected shape (see Payload). Without fields it is a
 * problem with the body as a whole; with fields, each one gets the same error code.
 */
final class InvalidPayloadException extends \RuntimeException
{
    /**
     * @param list<string>               $fields
     * @param array<string, scalar|null> $params
     */
    private function __construct(
        public readonly string $errorCode,
        public readonly array $fields = [],
        public readonly array $params = [],
    ) {
        parent::__construct(\sprintf('%s%s', $errorCode, [] !== $fields ? ': ' . \implode(', ', $fields) : ''));
    }

    public static function emptyBody(): self
    {
        return new self(ApiErrorCode::EMPTY_BODY);
    }

    public static function malformedJson(): self
    {
        return new self(ApiErrorCode::MALFORMED_JSON);
    }

    /**
     * Valid JSON that is not an object: [1], "x", null.
     */
    public static function invalidBody(): self
    {
        return new self(ApiErrorCode::INVALID_BODY);
    }

    /**
     * @param list<string> $fields
     */
    public static function unknownFields(array $fields): self
    {
        return new self(ApiErrorCode::UNKNOWN_FIELD, $fields);
    }

    /**
     * Same code as a Value Object rejecting a blank value, so clients see one "required" error.
     */
    public static function missingField(string $field): self
    {
        return new self(InvalidValueException::REQUIRED, [$field]);
    }

    public static function invalidType(string $field, string $expected): self
    {
        return new self(ApiErrorCode::INVALID_TYPE, [$field], ['expected' => $expected]);
    }
}
