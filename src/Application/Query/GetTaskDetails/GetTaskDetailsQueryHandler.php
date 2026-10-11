<?php

declare(strict_types=1);

namespace App\Application\Query\GetTaskDetails;

use App\Application\Query\QueryHandlerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;

final readonly class GetTaskDetailsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(GetTaskDetailsQuery $query): GetTaskDetailsResponse
    {
        $taskId = $query->getTaskId();

        $task = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new TaskNotFoundException($taskId);
        }

        return new GetTaskDetailsResponse($task);
    }
}
