<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * Documents ApiResponder::collection(): {"status": "success", "data": [<item>...], "meta": <meta>}.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class CollectionResponse extends OA\Response
{
    /**
     * @param class-string $item schema class of each element of `data`
     * @param class-string $meta schema class of `meta`
     */
    public function __construct(string $item, string $meta, string $description, int $response = Response::HTTP_OK)
    {
        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\JsonContent(
                required: ['status', 'data', 'meta'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['success'], example: 'success'),
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: $item))),
                    new OA\Property(property: 'meta', ref: new Model(type: $meta)),
                ],
                type: 'object',
            ),
        );
    }
}
