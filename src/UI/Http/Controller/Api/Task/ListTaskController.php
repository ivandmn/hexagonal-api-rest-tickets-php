<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\DTO\Views\Task\TaskListView;
use App\Application\Query\Task\ListTasks\ListTasksQuery;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\CollectionResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\OpenApi\Schema\TaskListMeta;
use App\UI\Http\Request\Payload;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Tasks')]
final class ListTaskController extends ApiController
{
    #[OA\Get(
        summary: 'List tasks',
        description: 'Paginated, newest first. An empty page is a 200 with an empty `data`; `meta.total` counts every task matching the filters.',
    )]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: TaskStatus::class))]
    #[OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: TaskPriority::class))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: ListTasksQuery::DEFAULT_LIMIT, maximum: ListTasksQuery::MAX_LIMIT, minimum: 1))]
    #[CollectionResponse(Task::class, TaskListMeta::class, 'One page of tasks')]
    #[ValidationErrorResponse([
        ['field' => 'status', 'code' => 'VALUE_INVALID_CHOICE', 'message' => 'This value must be one of: pending, in_progress, completed.', 'params' => ['allowed' => 'pending, in_progress, completed']],
        ['field' => 'limit', 'code' => 'VALUE_OUT_OF_RANGE', 'message' => 'This value must be between 1 and 100.', 'params' => ['min' => 1, 'max' => 100]],
    ])]
    #[InternalErrorResponse]
    #[Route('/api/tasks', name: 'api_task_list', methods: ['GET'])]
    public function __invoke(Request $request, ApiResponder $responder): JsonResponse
    {
        $query = Payload::fromQuery($request, ['status', 'priority', 'page', 'limit']);

        /** @var TaskListView $tasks */
        $tasks = $this->ask(new ListTasksQuery(
            $query->optionalString('status'),
            $query->optionalString('priority'),
            $query->optionalInt('page') ?? 1,
            $query->optionalInt('limit') ?? ListTasksQuery::DEFAULT_LIMIT,
        ));

        return $responder->collection($tasks->items, ['total' => $tasks->total, 'page' => $tasks->page, 'limit' => $tasks->limit]);
    }
}
