<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\UpdateTask\UpdateTaskResponse;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\Request\PayloadContext;
use App\UI\Http\Request\Task\UpdateTaskRequest;
use App\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Tasks')]
final class UpdateTaskController extends BaseController
{
    #[OA\Put(
        summary: 'Update a task',
        description: 'Only the non-null fields sent are updated; send `"dueDate": null` to remove the due date. Allowed status transitions: `pending` → `in_progress` → `completed`.',
    )]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateTaskRequest::class))]
    #[SuccessResponse(Task::class, 'Task updated')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'Task not found (also for an id that is not a UUID)', 'TASK_NOT_FOUND', 'Task 3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63 does not exist.', ['taskId' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63'])]
    #[ProblemResponse(
        Response::HTTP_CONFLICT,
        'Status transition not allowed',
        'TASK_STATUS_TRANSITION_NOT_ALLOWED',
        'A task cannot move from "completed" to "pending".',
        ['from' => 'completed', 'to' => 'pending'],
        [['field' => 'status', 'code' => 'TASK_STATUS_TRANSITION_NOT_ALLOWED', 'message' => 'A task cannot move from "completed" to "pending".', 'params' => ['from' => 'completed', 'to' => 'pending']]],
    )]
    #[ValidationErrorResponse([
        ['field' => 'priority', 'code' => 'VALUE_INVALID_CHOICE', 'message' => 'This value must be one of: low, medium, high.', 'params' => ['allowed' => 'low, medium, high']],
        ['field' => 'dueDate', 'code' => 'VALUE_INVALID_FORMAT', 'message' => 'Invalid format, expected RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00).', 'params' => ['expected' => 'RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00)']],
    ])]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}', name: 'api_task_update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    public function __invoke(
        string $id,
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json', serializationContext: PayloadContext::STRICT)]
        UpdateTaskRequest $payload,
    ): JsonResponse {
        // `"dueDate": null` removes it; leaving the key out keeps it
        $clearDueDate = $request->getPayload()->has('dueDate') && null === $request->getPayload()->get('dueDate');

        /** @var UpdateTaskResponse $response */
        $response = $this->commandHandler($payload->toCommand($id, $clearDueDate));

        return ApiResponse::success($response);
    }
}
