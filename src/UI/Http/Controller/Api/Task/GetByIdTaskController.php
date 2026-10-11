<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CommandBusInterface;
use App\Application\Query\GetTaskDetails\GetTaskDetailsQuery;
use App\Application\Query\GetTaskDetails\GetTaskDetailsResponse;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskIdException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GetByIdTaskController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[Route('/api/tasks/{id}', name: 'api_task_detail', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        try {
            $query = new GetTaskDetailsQuery($id);

            /** @var GetTaskDetailsResponse $response */
            $response = $this->queryHandler($query);

            return ApiResponse::success($response);
        } catch (InvalidTaskIdException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TaskNotFoundException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Getting Task Details: %s', $exception->getMessage()),
                ['exception' => $exception]
            );

            return ApiResponse::internalError();
        }
    }
}
