<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents the generic 500 every endpoint may return on unexpected errors.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class InternalErrorResponse extends ProblemResponse
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unexpected server error', ApiErrorCode::INTERNAL_ERROR, 'An unexpected error occurred.');
    }
}
