<?php

declare(strict_types=1);

namespace App\UI\Http\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Turns the request input (JSON body or query string) into the primitives that commands and
 * queries take. Only presence and type are checked here: what a value must look like is the
 * domain's call, made when the command builds its Value Objects.
 *
 * - Fields not in the allowed list are rejected (REQUEST_UNKNOWN_FIELD).
 * - The caller states the type it expects; any other type is rejected (REQUEST_INVALID_TYPE),
 *   nothing is cast. The query string has no types, so there numbers and booleans are parsed.
 * - Missing and null are different things: a required field that is missing is VALUE_REQUIRED,
 *   a null one is REQUEST_INVALID_TYPE. The optional readers return null for both; use has()
 *   to tell them apart (e.g. `"due_date": null` clears the due date, leaving it out keeps it).
 */
final readonly class Payload
{
    /**
     * @param array<array-key, mixed> $values
     * @param bool                    $typed false for the query string, where every value is a string
     */
    private function __construct(
        private array $values,
        private bool $typed,
    ) {
    }

    /**
     * @param list<string> $allowed
     *
     * @throws InvalidPayloadException
     * @throws UnsupportedMediaTypeHttpException
     */
    public static function fromJsonBody(Request $request, array $allowed): self
    {
        $format = $request->getContentTypeFormat();
        if (null !== $format && 'json' !== $format) {
            throw new UnsupportedMediaTypeHttpException(\sprintf('Unsupported format, expects "json" but "%s" given.', $format));
        }

        $content = \trim($request->getContent());
        if ('' === $content) {
            throw InvalidPayloadException::emptyBody();
        }

        try {
            $decoded = \json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidPayloadException::malformedJson();
        }

        // `[]` is how many clients encode an empty object.
        if ([] === $decoded) {
            $decoded = new \stdClass();
        }

        if (!$decoded instanceof \stdClass) {
            throw InvalidPayloadException::invalidBody();
        }

        return self::allowing(\get_object_vars($decoded), $allowed, typed: true);
    }

    /**
     * @param list<string> $allowed
     *
     * @throws InvalidPayloadException
     */
    public static function fromQuery(Request $request, array $allowed): self
    {
        return self::allowing($request->query->all(), $allowed, typed: false);
    }

    public function has(string $field): bool
    {
        return \array_key_exists($field, $this->values);
    }

    /**
     * @throws InvalidPayloadException missing (VALUE_REQUIRED), null or not a string
     */
    public function string(string $field): string
    {
        return $this->optionalString($this->required($field)) ?? throw InvalidPayloadException::invalidType($field, 'string');
    }

    /**
     * @throws InvalidPayloadException missing (VALUE_REQUIRED), null or not an integer
     */
    public function int(string $field): int
    {
        return $this->optionalInt($this->required($field)) ?? throw InvalidPayloadException::invalidType($field, 'int');
    }

    /**
     * @throws InvalidPayloadException missing (VALUE_REQUIRED), null or not a number
     */
    public function float(string $field): float
    {
        return $this->optionalFloat($this->required($field)) ?? throw InvalidPayloadException::invalidType($field, 'float');
    }

    /**
     * @throws InvalidPayloadException missing (VALUE_REQUIRED), null or not a boolean
     */
    public function bool(string $field): bool
    {
        return $this->optionalBool($this->required($field)) ?? throw InvalidPayloadException::invalidType($field, 'bool');
    }

    /**
     * Null when the field is missing or null.
     *
     * @throws InvalidPayloadException not a string
     */
    public function optionalString(string $field): ?string
    {
        $value = $this->value($field);

        return null === $value || \is_string($value) ? $value : throw InvalidPayloadException::invalidType($field, 'string');
    }

    /**
     * Null when the field is missing or null.
     *
     * @throws InvalidPayloadException not an integer
     */
    public function optionalInt(string $field): ?int
    {
        $value = $this->value($field);

        if (null === $value || \is_int($value)) {
            return $value;
        }

        if (!$this->typed && \is_string($value) && false !== $int = \filter_var($value, \FILTER_VALIDATE_INT)) {
            return $int;
        }

        throw InvalidPayloadException::invalidType($field, 'int');
    }

    /**
     * Null when the field is missing or null. JSON integers are numbers too.
     *
     * @throws InvalidPayloadException not a number
     */
    public function optionalFloat(string $field): ?float
    {
        $value = $this->value($field);

        if (null === $value) {
            return null;
        }

        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        if (!$this->typed && \is_string($value) && false !== $float = \filter_var($value, \FILTER_VALIDATE_FLOAT)) {
            return $float;
        }

        throw InvalidPayloadException::invalidType($field, 'float');
    }

    /**
     * Null when the field is missing or null. In the query string: true/false, 1/0, yes/no, on/off.
     *
     * @throws InvalidPayloadException not a boolean
     */
    public function optionalBool(string $field): ?bool
    {
        $value = $this->value($field);

        if (null === $value || \is_bool($value)) {
            return $value;
        }

        if (!$this->typed && \is_string($value) && null !== $bool = \filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE)) {
            return $bool;
        }

        throw InvalidPayloadException::invalidType($field, 'bool');
    }

    /**
     * @throws InvalidPayloadException when the field is missing
     */
    private function required(string $field): string
    {
        return $this->has($field) ? $field : throw InvalidPayloadException::missingField($field);
    }

    /**
     * The raw value; null when missing or null.
     */
    private function value(string $field): mixed
    {
        return \array_key_exists($field, $this->values) ? $this->values[$field] : null;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string>            $allowed
     *
     * @throws InvalidPayloadException
     */
    private static function allowing(array $values, array $allowed, bool $typed): self
    {
        $unknown = \array_values(\array_diff(\array_map(strval(...), \array_keys($values)), $allowed));
        if ([] !== $unknown) {
            throw InvalidPayloadException::unknownFields($unknown);
        }

        return new self($values, $typed);
    }
}
