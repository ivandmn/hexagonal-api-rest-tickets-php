<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\AssignTaskToUser\AssignTaskToUserCommand;
use App\Application\Command\AssignTaskToUser\AssignTaskToUserResponse;
use App\Application\Command\CommandBusInterface;
use App\Application\Query\QueryBusInterface;
use App\Domain\Exception\Task\InvalidTaskIdException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\Task\UserNotFoundException;
use App\Domain\Exception\User\InvalidUserIdException;
use App\Infrastructure\Exception\InvalidRequestException;
use App\Infrastructure\Exception\InvalidRequestParameterException;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Http\Request\Task\AssignTaskToUserDto;
use App\Infrastructure\Service\ApiRequestValidator;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\ErrorResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\TaskAssignment;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Tasks')]
class AssignTaskController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
        private readonly ApiRequestValidator $apiRequestValidator,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[OA\Patch(summary: 'Assign a task to a user')]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new Model(type: AssignTaskToUserDto::class))]
    #[SuccessResponse(TaskAssignment::class, 'Task assigned')]
    #[ErrorResponse(Response::HTTP_BAD_REQUEST, 'Invalid payload, task ID or user ID', 'Invalid User ID')]
    #[ErrorResponse(Response::HTTP_NOT_FOUND, 'Task or user not found', 'User with this ID does not exist')]
    #[InternalErrorResponse]
    #[Route('/api/tasks/{id}/assign', name: 'api_task_assign', methods: ['PATCH'])]
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            /** @var AssignTaskToUserDto $data */
            $data = $this->apiRequestValidator->validate($request, AssignTaskToUserDto::class);

            $command = new AssignTaskToUserCommand(
                $id,
                $data->userId,
            );

            /** @var AssignTaskToUserResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidRequestException|InvalidRequestParameterException|InvalidTaskIdException|InvalidUserIdException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (TaskNotFoundException|UserNotFoundException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Assigning Task To User: %s', $exception->getMessage()),
                ['exception' => $exception],
            );

            return ApiResponse::internalError();
        }
    }
}
