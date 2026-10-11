<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\DeleteTask\DeleteTaskCommand;
use App\Application\Command\DeleteTask\DeleteTaskResponse;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskIdException;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\ErrorResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\TaskDeletion;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Tasks')]
class DeleteTaskController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[OA\Delete(summary: 'Delete a task', description: 'Only tasks with status `pending` can be deleted.')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[SuccessResponse(TaskDeletion::class, 'Task deleted')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Malformed task ID', 'Invalid Task ID')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Task not found', 'Task with this ID does not exist')]
    #[ErrorResponse(Response::HTTP_CONFLICT, 'Task is not pending', 'Tasks with status "in_progress" cannot be deleted')]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}', name: 'api_task_delete', methods: ['DELETE'])]
    public function __invoke(string $id): JsonResponse
    {
        try {
            $command = new DeleteTaskCommand($id);

            /** @var DeleteTaskResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidTaskIdException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TaskNotFoundException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (TaskDeletionNotAllowedException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Deleting Task: %s', $exception->getMessage()),
                ['exception' => $exception]
            );

            return ApiResponse::internalError();
        }
    }
}
