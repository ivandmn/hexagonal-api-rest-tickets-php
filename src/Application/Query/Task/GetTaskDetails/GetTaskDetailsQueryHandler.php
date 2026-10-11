<?php

declare(strict_types=1);

namespace App\Application\Query\Task\GetTaskDetails;

use App\Application\DTO\Views\Task\TaskView;
use App\Application\Query\QueryHandlerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;

final readonly class GetTaskDetailsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(GetTaskDetailsQuery $query): TaskView
    {
        $task = $this->taskRepository->findById($query->getTaskId()) ?? throw new TaskNotFoundException($query->getTaskId());

        return TaskView::fromTask($task);
    }
}
