<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['status', 'reason'])]
final class Error
{
    #[OA\Property(enum: ['error'], example: 'error')]
    public string $status;

    #[OA\Property(description: 'Human readable error message', example: 'Invalid Task ID')]
    public string $reason;
}
