<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Attribute;

use App\UI\Http\OpenApi\Schema\Problem;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Documents an error built by App\UI\Http\Exception\ApiExceptionListener (Problem Details).
 * Status and message must match config/packages/error_mapping.yaml.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ProblemResponse extends OA\Response
{
    /**
     * @param string                     $code   error code (the Problem "type")
     * @param string                     $title  example of the public message
     * @param array<string, scalar|null> $params example params
     * @param list<array<string, mixed>> $errors example per-field errors
     */
    public function __construct(int $response, string $description, string $code, string $title, array $params = [], array $errors = [])
    {
        $example = ['type' => $code, 'title' => $title, 'status' => $response];
        if ([] !== $params) {
            $example['params'] = $params;
        }
        if ([] !== $errors) {
            $example['errors'] = $errors;
        }
        $example['trace_id'] = 'a3f9c2e17b4d8e05';

        parent::__construct(
            response: $response,
            description: $description,
            content: new OA\MediaType(
                mediaType: 'application/problem+json',
                schema: new OA\Schema(ref: new Model(type: Problem::class)),
                example: $example,
            ),
        );
    }
}
