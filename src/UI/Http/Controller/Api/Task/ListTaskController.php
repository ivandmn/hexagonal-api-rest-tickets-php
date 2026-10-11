<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CommandBusInterface;
use App\Application\Query\ListTasks\ListTasksQuery;
use App\Application\Query\ListTasks\ListTasksResponse;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskPriorityException;
use App\Domain\Exception\Task\InvalidTaskStatusException;
use App\Infrastructure\Exception\InvalidRequestException;
use App\Infrastructure\Exception\InvalidRequestParameterException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\Request\Task\ListTaskRequestDto;
use App\Infrastructure\Service\ApiRequestValidator;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
                $data->limit ? (int) $data->limit : 50,
            );

            /** @var ListTasksResponse $response */
            $response = $this->queryHandler($query);

            return $response->isEmpty() ? ApiResponse::empty() : ApiResponse::success($response);
        } catch (InvalidRequestParameterException|InvalidRequestException|InvalidTaskStatusException|InvalidTaskPriorityException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Listing Tasks: %s', $exception->getMessage()),
                ['exception' => $exception]
            );

            return ApiResponse::internalError();
        }
    }
}
