<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Component\HttpFoundation\Response;

/**
 * 422 returned when the payload or query string is invalid: one entry per invalid field.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class ValidationErrorResponse extends ProblemResponse
{
    /**
     * @param list<array<string, mixed>> $errors example per-field errors
     */
    public function __construct(array $errors)
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid payload (every invalid field is listed)', ApiErrorCode::VALIDATION_ERROR, 'The submitted data is not valid.', [], $errors);
    }
}
