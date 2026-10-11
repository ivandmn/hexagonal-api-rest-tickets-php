<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\Task\UnassignTask\UnassignTaskCommand;
use App\Application\DTO\Views\Task\TaskView;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Tasks')]
final class UnassignTaskController extends ApiController
{
    #[OA\Delete(summary: 'Unassign a task', description: 'Returns the updated task, with `assigned_to` null. Unassigning a task nobody has is not an error.')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[SuccessResponse(Task::class, 'Task unassigned')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'Task not found (also for an id that is not a UUID)', 'TASK_NOT_FOUND', 'Task 3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63 does not exist.', ['task_id' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63'])]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}/assign', name: 'api_task_unassign', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function __invoke(string $id, ApiResponder $responder): JsonResponse
    {
        /** @var TaskView $task */
        $task = $this->dispatch(new UnassignTaskCommand($id));

        return $responder->ok($task);
    }
}
