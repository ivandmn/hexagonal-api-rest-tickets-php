<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use App\UI\Http\OpenApi\Schema\Error;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Documents the `ApiResponse::error()` envelope: {"status": "error", "reason": "..."}.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ErrorResponse extends OA\Response
{
    public function __construct(int $response, string $description, string $example)
    {
        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\JsonContent(
                ref: new Model(type: Error::class),
                example: ['status' => 'error', 'reason' => $example],
            ),
        );
    }
}
