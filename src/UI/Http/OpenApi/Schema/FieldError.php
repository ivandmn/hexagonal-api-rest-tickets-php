<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'One error tied to a payload field', required: ['field', 'code', 'message', 'params'])]
final class FieldError
{
    #[OA\Property(description: 'Payload field (property path), or null when the error is not tied to one', example: 'title', nullable: true)]
    public ?string $field;

    #[OA\Property(description: 'Stable error code: branch on this, never on the message', example: 'VALUE_TOO_SHORT')]
    public string $code;

    #[OA\Property(description: 'Human readable message', example: 'This value must be at least 5 characters long.')]
    public string $message;

    /** @var array<string, scalar|null> */
    #[OA\Property(description: 'Context of the error (limits, allowed values...)', type: 'object', example: ['min' => 5], additionalProperties: true)]
    public array $params;
}
