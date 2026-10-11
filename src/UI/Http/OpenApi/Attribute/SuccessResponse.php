<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents the `ApiResponse::success()` envelope: {"status": "success", "data": <model>}.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SuccessResponse extends OA\Response
{
    /**
     * @param class-string $model    Schema class describing the `data` payload
     * @param bool         $location whether a Location header points to the resource (201)
     */
    public function __construct(string $model, string $description, int $response = Response::HTTP_OK, bool $location = false)
    {
        parent::__construct(
            response: $response,
            description: $description,
            headers: $location ? [new OA\Header(header: 'Location', description: 'URL of the created resource', schema: new OA\Schema(type: 'string', format: 'uri'))] : null,
            content: new OA\JsonContent(
                required: ['status', 'data'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                    new OA\Property(property: 'data', ref: new Model(type: $model)),
                ],
                type: 'object',
            ),
        );
    }
}
