<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\CreateTask\CreateTaskCommand;
use App\Application\Command\CreateTask\CreateTaskResponse;
use App\Application\Exception\InvalidDateFormat;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskDescription;
use App\Domain\Exception\Task\InvalidTaskPriorityException;
use App\Domain\Exception\Task\InvalidTaskTitleException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Infrastructure\Exception\InvalidRequestException;
use App\Infrastructure\Exception\InvalidRequestParameterException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\Request\Task\CreateTaskRequestDto;
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
class CreateTaskController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
        private readonly ApiRequestValidator $apiRequestValidator,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[OA\Post(summary: 'Create a task', description: 'New tasks start with status `pending`.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateTaskRequestDto::class))]
    #[SuccessResponse(Task::class, 'Task created')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid payload', 'Task title does not reach minimum characters length')]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'Due date is in the past', 'Due date cannot be in the past')]
    #[InternalErrorResponse]
    #[Route('/api/tasks', name: 'api_task_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            /** @var CreateTaskRequestDto $data */
            $data = $this->apiRequestValidator->validate($request, CreateTaskRequestDto::class);

            $command = new CreateTaskCommand(
                $data->title,
                $data->description,
                $data->priority,
                $data->dueDate,
            );

            /** @var CreateTaskResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidDateFormat|InvalidRequestException|InvalidRequestParameterException|InvalidTaskDescription|InvalidTaskPriorityException|InvalidTaskTitleException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TaskDueDateInPastException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Creating Task: %s', $exception->getMessage()),
                ['exception' => $exception],
            );

            return ApiResponse::internalError();
        }
    }
}
