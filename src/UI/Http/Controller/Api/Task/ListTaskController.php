<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Query\ListTasks\ListTasksQuery;
use App\Application\Query\ListTasks\ListTasksResponse;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\TaskList;
use App\UI\Http\Request\PayloadContext;
use App\UI\Http\Request\Task\ListTasksRequest;
use App\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Tasks')]
final class ListTaskController extends BaseController
{
    #[OA\Get(
        summary: 'List tasks',
        description: 'Paginated, newest first. `meta.total` is the number of tasks matching the filters across all pages.',
    )]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: TaskStatus::class))]
    #[OA\Parameter(name: 'priority', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: TaskPriority::class))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: ListTasksQuery::DEFAULT_LIMIT, maximum: ListTasksQuery::MAX_LIMIT, minimum: 1))]
    #[SuccessResponse(TaskList::class, 'Tasks found')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'No tasks match the filters')]
    #[ValidationErrorResponse([
        ['field' => 'status', 'code' => 'VALUE_INVALID_CHOICE', 'message' => 'This value must be one of: pending, in_progress, completed.', 'params' => ['allowed' => 'pending, in_progress, completed']],
        ['field' => 'limit', 'code' => 'VALUE_OUT_OF_RANGE', 'message' => 'This value must be between 1 and 100.', 'params' => ['min' => 1, 'max' => 100]],
    ])]
    #[InternalErrorResponse]
    #[Route('/api/tasks', name: 'api_task_list', methods: ['GET'])]
    public function __invoke(
        #[MapQueryString(serializationContext: PayloadContext::STRICT)]
        ListTasksRequest $query = new ListTasksRequest(),
    ): JsonResponse {
        /** @var ListTasksResponse $response */
        $response = $this->queryHandler($query->toQuery());

        return $response->isEmpty() ? ApiResponse::empty() : ApiResponse::success($response);
    }
}
