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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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

    #[Route('/api/tasks/{id}/assign', name: 'api_task_assign', methods: ['PATCH'])]
    public function __invoke(Request $request, string $id): JsonResponse
    {
        try {
            /** @var AssignTaskToUserDto $data */
            $data = $this->apiRequestValidator->validate($request, AssignTaskToUserDto::class);

            $command = new AssignTaskToUserCommand(
                $id,
                $data->userId
            );

            /** @var AssignTaskToUserResponse $response */
            $response = $this->commandHandler($command);

            return ApiResponse::success($response);
        } catch (InvalidRequestParameterException|InvalidRequestException|InvalidUserIdException|InvalidTaskIdException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (UserNotFoundException|TaskNotFoundException $exception) {
            return ApiResponse::error($exception->getMessage(), Response::HTTP_NOT_FOUND);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Assigning Task To User: %s', $exception->getMessage()),
                ['exception' => $exception]
            );

            return ApiResponse::internalError();
        }
    }
}
