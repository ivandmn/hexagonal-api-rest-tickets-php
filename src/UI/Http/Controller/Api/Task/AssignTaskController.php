<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\AssignTaskToUser\AssignTaskToUserResponse;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\TaskAssignment;
use App\UI\Http\Request\PayloadContext;
use App\UI\Http\Request\Task\AssignTaskRequest;
use App\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Tasks')]
final class AssignTaskController extends BaseController
{
    #[OA\Patch(summary: 'Assign a task to a user')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new Model(type: AssignTaskRequest::class))]
    #[SuccessResponse(TaskAssignment::class, 'Task assigned')]
    #[ProblemResponse(
        Response::HTTP_NOT_FOUND,
        'Task or user not found (TASK_NOT_FOUND or USER_NOT_FOUND)',
        'USER_NOT_FOUND',
        'User b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e does not exist.',
        ['userId' => 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e'],
    )]
    #[ValidationErrorResponse([
        ['field' => 'userId', 'code' => 'VALUE_INVALID_FORMAT', 'message' => 'Invalid format, expected UUID.', 'params' => ['expected' => 'UUID']],
    ])]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}/assign', name: 'api_task_assign', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function __invoke(
        string $id,
        #[MapRequestPayload(acceptFormat: 'json', serializationContext: PayloadContext::STRICT)]
        AssignTaskRequest $payload,
    ): JsonResponse {
        /** @var AssignTaskToUserResponse $response */
        $response = $this->commandHandler($payload->toCommand($id));

        return ApiResponse::success($response);
    }
}
