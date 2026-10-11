<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Application\Command\UpdateTask\UpdateTaskResponse;
use App\Application\Exception\InvalidDateFormat;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskDescription;
use App\Domain\Exception\Task\InvalidTaskIdException;
use App\Domain\Exception\Task\InvalidTaskPriorityException;
use App\Domain\Exception\Task\InvalidTaskStatusException;
use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\InvalidTaskTitleException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Infrastructure\Exception\InvalidRequestException;
use App\Infrastructure\Exception\InvalidRequestParameterException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\Request\Task\UpdateTaskRequestDto;
use App\Infrastructure\Service\ApiRequestValidator;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UpdateTaskController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
        private readonly ApiRequestValidator $apiRequestValidator,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[Route('/api/tasks/{id}', name: 'api_task_update', methods: ['PUT'])]
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            /** @var UpdateTaskRequestDto $data */
            $data = $this->apiRequestValidator->validate($request, UpdateTaskRequestDto::class);

            $command = new UpdateTaskCommand(
                $id,
                $data->title,
                $data->description,
                $data->status,
                $data->priority,
                $data->dueDate
            );

            /** @var UpdateTaskResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidRequestParameterException|InvalidRequestException|InvalidTaskIdException|InvalidTaskTitleException|InvalidTaskDescription|InvalidDateFormat|InvalidTaskStatusException|InvalidTaskPriorityException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TaskNotFoundException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (TaskDueDateInPastException|InvalidTaskStatusTransitionException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Updating Task: %s', $exception->getMessage()),
                ['exception' => $exception]
            );

            return ApiResponse::internalError();
        }
    }
}
