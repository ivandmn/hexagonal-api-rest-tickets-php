<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use App\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents the `ApiResponse::internalError()` fallback every endpoint returns on unexpected errors.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class InternalErrorResponse extends ErrorResponse
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unexpected server error', ApiResponse::UNKNOWN_ERROR);
    }
}
