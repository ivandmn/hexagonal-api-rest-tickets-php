<?php

declare(strict_types=1);

namespace App\UI\Http\Exception;

/**
 * Error codes owned by the HTTP layer itself: the error envelope and problems with the shape of
 * the request (as opposed to business errors, which live in App\Domain\Exception).
 * Mapped in config/packages/error_mapping.yaml like every other code.
 */
final class ApiErrorCode
{
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const INTERNAL_ERROR   = 'INTERNAL_ERROR';

    public const MALFORMED_JSON = 'REQUEST_MALFORMED_JSON';
    public const EMPTY_BODY     = 'REQUEST_EMPTY_BODY';
    public const INVALID_BODY   = 'REQUEST_INVALID_BODY';
    public const UNKNOWN_FIELD  = 'REQUEST_UNKNOWN_FIELD';
    public const INVALID_TYPE   = 'REQUEST_INVALID_TYPE';

    private function __construct()
    {
    }
}
