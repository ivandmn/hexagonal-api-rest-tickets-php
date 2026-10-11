<?php

declare(strict_types=1);

namespace App\UI\Http\Exception;

/**
 * HTTP representation of one error code, as declared in config/packages/error_mapping.yaml.
 */
final readonly class ErrorMapping
{
    private const KEYS = ['status', 'message', 'field'];

    /**
     * @param int         $status  HTTP status
     * @param string      $message public message; may contain {placeholders} filled with the exception params
     * @param string|null $field   payload field the error belongs to, if any
     */
    public function __construct(
        public int $status,
        public string $message,
        public ?string $field = null,
    ) {
    }

    /**
     * Builds a mapping from its YAML definition, failing loudly on any mistake so a broken
     * entry is caught when the container boots instead of when the error happens.
     *
     * @throws \LogicException
     */
    public static function fromConfig(string $errorCode, mixed $definition): self
    {
        if (!\is_array($definition)) {
            throw self::invalid($errorCode, 'expected a map with "status" and "message"');
        }

        if ([] !== $unknown = \array_diff(\array_keys($definition), self::KEYS)) {
            throw self::invalid($errorCode, \sprintf('unknown key(s) "%s", allowed: %s', \implode('", "', $unknown), \implode(', ', self::KEYS)));
        }

        $status  = $definition['status'] ?? null;
        $message = $definition['message'] ?? null;
        $field   = $definition['field'] ?? null;

        if (!\is_int($status) || $status < 400 || $status > 599) {
            throw self::invalid($errorCode, '"status" must be an integer between 400 and 599');
        }

        if (!\is_string($message) || '' === \trim($message)) {
            throw self::invalid($errorCode, '"message" must be a non-empty string');
        }

        if (null !== $field && (!\is_string($field) || '' === \trim($field))) {
            throw self::invalid($errorCode, '"field" must be a non-empty string when present');
        }

        return new self($status, $message, $field);
    }

    private static function invalid(string $errorCode, string $reason): \LogicException
    {
        return new \LogicException(\sprintf('Invalid error mapping for "%s" in config/packages/error_mapping.yaml: %s.', $errorCode, $reason));
    }
}
