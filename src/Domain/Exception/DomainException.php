<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Base of every business exception.
 *
 * The domain knows nothing about HTTP: it only states *what* went wrong through a stable
 * `errorCode` (UPPER_SNAKE_CASE, prefixed by context, never reused) plus context `params`.
 * How that becomes a response (status, public message, payload field) is decided by the
 * HTTP layer (config/packages/error_mapping.yaml).
 *
 * The native integer `code` and the `message` keep their standard PHP meaning: the message is
 * internal (logs, debugging) and is never shown to API clients.
 */
abstract class DomainException extends \DomainException
{
    /**
     * @param string                     $errorCode stable business code (NOT the native integer code)
     * @param string                     $message   internal message, for logs
     * @param array<string, scalar|null> $params    context used to build the public message
     */
    public function __construct(
        private readonly string $errorCode,
        string $message = '',
        private readonly array $params = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    final public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, scalar|null>
     */
    final public function params(): array
    {
        return $this->params;
    }
}
