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
use App\UI\Http\OpenApi\Attribute\ErrorResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\Task;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Tasks')]
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

    #[OA\Put(
        summary: 'Update a task',
        description: 'Only the non-null fields sent are updated; send `"dueDate": null` to remove the due date. Allowed status transitions: `pending` → `in_progress` → `completed`.',
    )]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new Model(type: UpdateTaskRequestDto::class))]
    #[SuccessResponse(Task::class, 'Task updated')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid payload or task ID', 'Invalid Task ID')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Task not found', 'Task with this ID does not exist')]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'Status transition not allowed or due date in the past', 'Cannot transition from "completed" to "pending"')]
    #[InternalErrorResponse]
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
                $data->dueDate,
                // `"dueDate": null` removes it; leaving the key out keeps it
                clearDueDate: $request->getPayload()->has('dueDate') && null === $request->getPayload()->get('dueDate'),
            );

            /** @var UpdateTaskResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidDateFormat|InvalidRequestException|InvalidRequestParameterException|InvalidTaskDescription|InvalidTaskIdException|InvalidTaskPriorityException|InvalidTaskStatusException|InvalidTaskTitleException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TaskNotFoundException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (InvalidTaskStatusTransitionException|TaskDueDateInPastException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Updating Task: %s', $exception->getMessage()),
                ['exception' => $exception],
            );

            return ApiResponse::internalError();
        }
    }
}
