<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Problem Details (RFC 9457), served as application/problem+json', required: ['type', 'title', 'status', 'traceId'])]
final class Problem
{
    #[OA\Property(description: 'Stable error code: branch on this, never on the title', example: 'TASK_NOT_FOUND')]
    public string $type;

    #[OA\Property(description: 'Human readable message', example: 'Task 3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63 does not exist.')]
    public string $title;

    #[OA\Property(description: 'HTTP status, repeated for convenience', example: 404)]
    public int $status;

    /** @var array<string, scalar|null> */
    #[OA\Property(description: 'Context of a business error', type: 'object', example: ['taskId' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63'], additionalProperties: true)]
    public array $params;

    /** @var list<FieldError> */
    #[OA\Property(description: 'Per-field errors (validation errors, or business errors tied to a field)', type: 'array', items: new OA\Items(ref: new Model(type: FieldError::class)))]
    public array $errors;

    #[OA\Property(description: 'Correlates the response with the logs (X-Request-Id header if sent)', example: 'a3f9c2e17b4d8e05')]
    public string $traceId;
}
