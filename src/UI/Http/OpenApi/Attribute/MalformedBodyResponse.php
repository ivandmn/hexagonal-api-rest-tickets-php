<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents the 400 every endpoint with a JSON body may return: invalid JSON, empty body, or a
 * body that is not a JSON object (REQUEST_MALFORMED_JSON, REQUEST_EMPTY_BODY, REQUEST_INVALID_BODY).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class MalformedBodyResponse extends ProblemResponse
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'Malformed request body: invalid JSON (REQUEST_MALFORMED_JSON), empty (REQUEST_EMPTY_BODY) or not a JSON object (REQUEST_INVALID_BODY)',
            ApiErrorCode::MALFORMED_JSON,
            'The request body is not valid JSON.',
        );
    }
}
