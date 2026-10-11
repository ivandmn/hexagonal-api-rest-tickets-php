<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\DTO\Views\TaskView;
use App\Application\Query\GetTaskDetails\GetTaskDetailsQuery;
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
final class GetByIdTaskController extends ApiController
{
    #[OA\Get(summary: 'Get task details')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[SuccessResponse(Task::class, 'Task details')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'Task not found (also for an id that is not a UUID)', 'TASK_NOT_FOUND', 'Task 3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63 does not exist.', ['task_id' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63'])]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}', name: 'api_task_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ApiResponder $responder): JsonResponse
    {
        /** @var TaskView $task */
        $task = $this->ask(new GetTaskDetailsQuery($id));

        return $responder->ok($task);
    }
}
