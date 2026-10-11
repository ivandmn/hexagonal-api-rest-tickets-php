<?php

declare(strict_types=1);

namespace App\Application\Query\GetTaskDetails;

use App\Application\Query\QueryHandlerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskId;

final readonly class GetTaskDetailsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(GetTaskDetailsQuery $request): GetTaskDetailsResponse
    {
        $taskId = TaskId::fromString($request->getTaskId());

        $task = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new TaskNotFoundException('Task with this ID does not exist');
        }

        return new GetTaskDetailsResponse($task);
    }
}
