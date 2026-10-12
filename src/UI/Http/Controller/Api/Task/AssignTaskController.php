<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\Task\AssignTaskToUser\AssignTaskToUserCommand;
use App\Application\DTO\Views\Task\TaskView;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\MalformedBodyResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\Request\Payload;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Tasks')]
final class AssignTaskController extends ApiController
{
    #[OA\Patch(summary: 'Assign a task to a user', description: 'Returns the updated task.')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['user_id'],
        properties: [new OA\Property(property: 'user_id', type: 'string', format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')],
    ))]
    #[SuccessResponse(Task::class, 'Task assigned')]
    #[ProblemResponse(
        Response::HTTP_NOT_FOUND,
        'Task or user not found (TASK_NOT_FOUND or USER_NOT_FOUND)',
        'USER_NOT_FOUND',
        'User b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e does not exist.',
        ['user_id' => 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e'],
    )]
    #[ValidationErrorResponse([
        ['field' => 'user_id', 'code' => 'VALUE_INVALID_FORMAT', 'message' => 'Invalid format, expected UUID.', 'params' => ['expected' => 'UUID']],
    ])]
    #[MalformedBodyResponse]
    #[InternalErrorResponse]
    #[Route('/tasks/{id}/assign', name: 'api_task_assign', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function __invoke(string $id, Request $request, ApiResponder $responder): JsonResponse
    {
        $payload = Payload::fromJsonBody($request, ['user_id']);

        /** @var TaskView $task */
        $task = $this->dispatch(new AssignTaskToUserCommand($id, $payload->string('user_id')));

        return $responder->ok($task);
    }
}
