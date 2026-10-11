<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\DeleteTask\DeleteTaskCommand;
use App\Application\Command\DeleteTask\DeleteTaskResponse;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\TaskDeletion;
use App\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Tasks')]
final class DeleteTaskController extends BaseController
{
    #[OA\Delete(summary: 'Delete a task', description: 'Only tasks with status `pending` can be deleted.')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[SuccessResponse(TaskDeletion::class, 'Task deleted')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'Task not found (also for an id that is not a UUID)', 'TASK_NOT_FOUND', 'Task 3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63 does not exist.', ['taskId' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63'])]
    #[ProblemResponse(Response::HTTP_CONFLICT, 'Task is not pending', 'TASK_DELETION_NOT_ALLOWED', 'Only pending tasks can be deleted; this one is "in_progress".', ['taskId' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63', 'status' => 'in_progress'])]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}', name: 'api_task_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $id): JsonResponse
    {
        /** @var DeleteTaskResponse $response */
        $response = $this->commandHandler(new DeleteTaskCommand($id));

        return ApiResponse::success($response);
    }
}
