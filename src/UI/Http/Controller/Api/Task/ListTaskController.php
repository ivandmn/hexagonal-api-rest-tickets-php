<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CommandBusInterface;
use App\Application\Query\ListTasks\ListTasksQuery;
use App\Application\Query\ListTasks\ListTasksResponse;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskPriorityException;
use App\Domain\Exception\Task\InvalidTaskStatusException;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Infrastructure\Exception\InvalidRequestException;
use App\Infrastructure\Exception\InvalidRequestParameterException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\Request\Task\ListTaskRequestDto;
use App\Infrastructure\Service\ApiRequestValidator;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\ErrorResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\TaskList;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Tasks')]
class ListTaskController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
        private readonly ApiRequestValidator $apiRequestValidator,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

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
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid query parameters', 'Invalid task status')]
    #[InternalErrorResponse]
    #[Route('/api/tasks', name: 'api_task_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var ListTaskRequestDto $data */
            $data = $this->apiRequestValidator->validate($request, ListTaskRequestDto::class);

            $query = new ListTasksQuery(
                $data->status,
                $data->priority,
                $data->page ? (int) $data->page : 1,
                $data->limit ? (int) $data->limit : ListTasksQuery::DEFAULT_LIMIT,
            );

            /** @var ListTasksResponse $response */
            $response = $this->queryHandler($query);

            return $response->isEmpty() ? ApiResponse::empty() : ApiResponse::success($response);
        } catch (InvalidRequestException|InvalidRequestParameterException|InvalidTaskPriorityException|InvalidTaskStatusException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Listing Tasks: %s', $exception->getMessage()),
                ['exception' => $exception],
            );

            return ApiResponse::internalError();
        }
    }
}
